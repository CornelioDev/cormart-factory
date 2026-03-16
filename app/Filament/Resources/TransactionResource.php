<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Company;
use App\Models\Financing;
use App\Models\Transaction;
use App\Services\TransactionService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon  = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationLabel = 'Transacciones';
    protected static ?string $navigationGroup = 'Operaciones';
    protected static ?string $modelLabel      = 'Transacción';
    protected static ?string $pluralModelLabel = 'Transacciones';
    protected static ?int    $navigationSort  = 3;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if ($user->hasRole('company_user')) {
            $query->where('type', 'collection')
                  ->where('company_id', $user->company_id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        // Pre-carga desde query params cuando viene de FinancingResource
        $authUser      = auth()->user();
        $isInternal    = $authUser->hasAnyRole(['super_admin', 'operator']);
        $qType         = $isInternal ? request()->query('type') : 'collection';
        $qCompanyId    = $isInternal
            ? (request()->query('company_id') ? (int) request()->query('company_id') : null)
            : $authUser->company_id;
        $qFinancingIds = request()->query('financing_ids')
            ? array_map('intval', explode(',', request()->query('financing_ids')))
            : [];

        return $form->schema([
            // ── Tipo y compañía ──────────────────────────────────────────────
            Select::make('type')
                ->label('Tipo')
                ->required()
                ->options(function () use ($isInternal): array {
                    if (! $isInternal) {
                        return ['collection' => 'Cobro (Deudor → Fondo)'];
                    }
                    return [
                        'disbursement' => 'Desembolso (Fondo → Compañía)',
                        'collection'   => 'Cobro (Deudor → Fondo)',
                    ];
                })
                ->default($qType)
                ->disabled(! $isInternal)
                ->dehydrated()
                ->live()
                ->afterStateUpdated(function (Set $set) {
                    $set('financing_ids', []);
                    $set('amount', null);
                }),

            Select::make('company_id')
                ->label('Compañía')
                ->searchable()
                ->preload()
                ->relationship('company', 'name', fn ($query) => $query->where('active', true))
                ->default($qCompanyId)
                ->disabled(! $isInternal)
                ->dehydrated()
                ->live()
                ->afterStateUpdated(function (Set $set) use ($isInternal) {
                    if ($isInternal) {
                        $set('financing_ids', []);
                        $set('amount', null);
                    }
                }),

            // ── Financiamientos vinculados ───────────────────────────────────
            Select::make('financing_ids')
                ->label('Financiamientos')
                ->multiple()
                ->required()
                ->default($qFinancingIds)
                ->live()
                ->options(function (Get $get) use ($qType, $qCompanyId, $qFinancingIds): array {
                    $type      = $get('type') ?? $qType;
                    $companyId = $get('company_id') ?? $qCompanyId;

                    if (! $type || ! $companyId) {
                        return [];
                    }

                    $statuses = $type === 'disbursement'
                        ? ['solicited']
                        : ['disbursed', 'partially_collected'];

                    $query = Financing::query()
                        ->where('company_id', $companyId)
                        ->where(function ($q) use ($statuses, $qFinancingIds) {
                            $q->whereIn('status', $statuses);
                            if (! empty($qFinancingIds)) {
                                $q->orWhereIn('id', $qFinancingIds);
                            }
                        });

                    return $query->with('client')->get()
                        ->mapWithKeys(fn (Financing $f): array => [
                            $f->id => sprintf(
                                '%s — %s — RD$ %s%s',
                                $f->code ?? ('FN' . str_pad($f->id, 6, '0', STR_PAD_LEFT)),
                                $f->client->name,
                                number_format($f->amount, 2, '.', ','),
                                $f->collected_amount > 0
                                    ? ' (Pendiente: RD$ ' . number_format($f->remainingBalance(), 2, '.', ',') . ')'
                                    : ''
                            ),
                        ])
                        ->toArray();
                })
                ->afterStateUpdated(function (Get $get, Set $set, array $state): void {
                    $type = $get('type');
                    if ($type === 'collection') {
                        $remaining = (new TransactionService())->calculateRemainingBalance($state);
                        $set('amount', $remaining > 0 ? number_format($remaining, 2, '.', ',') : null);
                    } else {
                        $amount = (new TransactionService())->calculateAmount($type ?? '', $state);
                        $set('amount', $amount > 0 ? number_format($amount, 2, '.', ',') : null);
                    }
                })
                ->helperText('Selecciona el tipo y la compañía primero')
                ->columnSpanFull(),

            // ── Balance pendiente (solo para cobros) ───────────────────────
            Placeholder::make('remaining_balance')
                ->label('Balance Pendiente')
                ->content(function (Get $get): string {
                    $ids = $get('financing_ids') ?? [];
                    if (empty($ids) || $get('type') !== 'collection') {
                        return '—';
                    }
                    $remaining = (new TransactionService())->calculateRemainingBalance($ids);
                    return 'RD$ ' . number_format($remaining, 2, '.', ',');
                })
                ->visible(fn (Get $get): bool => $get('type') === 'collection'),

            // ── Monto ──────────────────────────────────────────────────────
            TextInput::make('amount')
                ->label(fn (Get $get) => $get('type') === 'collection' ? 'Monto a Cobrar' : 'Monto Total')
                ->prefix('RD$')
                ->required()
                ->numeric()
                ->step(0.01)
                ->disabled(fn (Get $get): bool => $get('type') !== 'collection')
                ->dehydrated()
                ->default(function () use ($qType, $qFinancingIds): ?string {
                    if (! $qType || empty($qFinancingIds)) {
                        return null;
                    }
                    if ($qType === 'collection') {
                        $amount = (new TransactionService())->calculateRemainingBalance($qFinancingIds);
                    } else {
                        $amount = (new TransactionService())->calculateAmount($qType, $qFinancingIds);
                    }
                    return $amount > 0 ? number_format($amount, 2, '.', ',') : null;
                })
                ->formatStateUsing(fn ($state) => $state ? number_format((float) str_replace(',', '', $state), 2, '.', ',') : null)
                ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace(',', '', $state) : null)
                ->helperText(fn (Get $get) => $get('type') === 'collection' ? 'Puede ingresar un monto parcial (abono) o el total' : null),

            // ── Datos bancarios ──────────────────────────────────────────────
            Select::make('bank')
                ->label('Banco')
                ->required()
                ->options([
                    'BanReservas'    => 'BanReservas',
                    'BHD'            => 'BHD',
                    'Banco Popular'  => 'Banco Popular',
                ]),

            TextInput::make('transaction_number')
                ->label('Número de Transacción')
                ->required()
                ->maxLength(255),

            DatePicker::make('transaction_date')
                ->label('Fecha de Transacción')
                ->required()
                ->default(now())
                ->displayFormat('d/m/Y'),

            Textarea::make('notes')
                ->label('Notas')
                ->maxLength(500)
                ->nullable()
                ->columnSpanFull(),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Datos de la Transacción')
                ->columns(3)
                ->schema([
                    TextEntry::make('code')
                        ->label('Código')
                        ->fontFamily('mono')
                        ->copyable(),

                    TextEntry::make('type')
                        ->label('Tipo')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'disbursement' => 'info',
                            'collection'   => 'success',
                            default        => 'gray',
                        })
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'disbursement' => 'Desembolso',
                            'collection'   => 'Cobro',
                            default        => $state,
                        }),

                    TextEntry::make('status')
                        ->label('Estado')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'pending'   => 'warning',
                            'confirmed' => 'success',
                            default     => 'gray',
                        })
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'pending'   => 'Pendiente',
                            'confirmed' => 'Confirmada',
                            default     => $state,
                        }),

                    TextEntry::make('company.name')
                        ->label('Compañía')
                        ->placeholder('—'),

                    TextEntry::make('amount')
                        ->label('Monto Total')
                        ->money('DOP', locale: 'es_DO'),

                    TextEntry::make('bank')
                        ->label('Banco'),

                    TextEntry::make('transaction_number')
                        ->label('No. Transacción')
                        ->copyable(),

                    TextEntry::make('transaction_date')
                        ->label('Fecha')
                        ->date('d/m/Y'),

                    TextEntry::make('notes')
                        ->label('Notas')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),

            Section::make('Financiamientos Asociados')
                ->schema([
                    RepeatableEntry::make('financings')
                        ->label('')
                        ->columns(5)
                        ->schema([
                            TextEntry::make('code')
                                ->label('Código')
                                ->url(fn (Financing $record): string =>
                                    FinancingResource::getUrl('view', ['record' => $record])
                                )
                                ->openUrlInNewTab()
                                ->color('primary')
                                ->weight(\Filament\Support\Enums\FontWeight::SemiBold),

                            TextEntry::make('client.name')
                                ->label('Deudor'),

                            TextEntry::make('amount')
                                ->label('Monto')
                                ->money('DOP', locale: 'es_DO'),

                            TextEntry::make('commission')
                                ->label('Comisión')
                                ->money('DOP', locale: 'es_DO'),

                            TextEntry::make('status')
                                ->label('Estado')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'solicited'            => 'warning',
                                    'disbursed'            => 'info',
                                    'partially_collected'  => 'primary',
                                    'collected'            => 'success',
                                    'cancelled'            => 'danger',
                                    default                => 'gray',
                                })
                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                    'solicited'            => 'Solicitado',
                                    'disbursed'            => 'Desembolsado',
                                    'partially_collected'  => 'Abonado',
                                    'collected'            => 'Cobrado',
                                    'cancelled'            => 'Cancelado',
                                    default                => $state,
                                }),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->url(fn (Transaction $record): string =>
                        static::getUrl('view', ['record' => $record])
                    )
                    ->color('primary'),

                TextColumn::make('financings.client.name')
                    ->label('Deudor')
                    ->searchable()
                    ->limit(20)
                    ->tooltip(fn ($record): ?string => $record->financings->pluck('client.name')->join(', '))
                    ->placeholder('—'),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('DOP', locale: 'es_DO')
                    ->sortable(),

                TextColumn::make('bank')
                    ->label('Banco')
                    ->searchable(),

                TextColumn::make('transaction_number')
                    ->label('No. Transacción')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('transaction_date')
                    ->label('Fecha')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'disbursement' => 'info',
                        'collection'   => 'success',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'disbursement' => 'Desembolso',
                        'collection'   => 'Cobro',
                    }),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'disbursement' => 'Desembolso',
                        'collection'   => 'Cobro',
                    ]),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending'   => 'Pendiente',
                        'confirmed' => 'Confirmada',
                    ]),
            ])
            ->actions([
                Action::make('confirm')
                    ->label('Confirmar cobro')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Transaction $record): bool =>
                        $record->status === 'pending'
                        && $record->type === 'collection'
                        && auth()->user()->hasAnyRole(['super_admin', 'operator'])
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar cobro')
                    ->modalDescription('¿Confirmas que este pago fue recibido y verificado?')
                    ->action(function (Transaction $record): void {
                        (new TransactionService())->confirm($record);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'view'   => Pages\ViewTransaction::route('/{record}'),
        ];
    }
}
