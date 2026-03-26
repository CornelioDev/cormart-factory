<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinancingResource\Pages;
use App\Models\Client;
use App\Models\Financing;
use App\Models\Parameter;
use App\Models\Transaction;
use App\Services\FinancingService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Support\RawJs;
use Filament\Forms\Set;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FinancingResource extends Resource
{
    protected static ?string $model = Financing::class;

    protected static ?string $navigationIcon   = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel  = 'Financiamientos';
    protected static ?string $navigationGroup  = 'Operaciones';
    protected static ?string $modelLabel       = 'Financiamiento';
    protected static ?string $pluralModelLabel = 'Financiamientos';
    protected static ?int    $navigationSort   = 4;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if ($user->hasRole('company_user')) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        $defaultTerm = (int) Parameter::where('key', 'default_term_days')->value('value') ?? 15;

        return $form->schema([
            // ── Datos principales ────────────────────────────────────────────
            Select::make('company_id')
                ->label('Compañía')
                ->required()
                ->searchable()
                ->preload()
                ->relationship('company', 'name', fn ($query) => $query->where('active', true))
                ->createOptionForm([
                    TextInput::make('name')
                        ->label('Nombre / Razón Social')
                        ->required()
                        ->unique()
                        ->maxLength(255),
                    TextInput::make('rnc')
                        ->label('RNC')
                        ->maxLength(20),
                    TextInput::make('contact_name')
                        ->label('Nombre de Contacto')
                        ->maxLength(255),
                    TextInput::make('contact_email')
                        ->label('Correo de Contacto')
                        ->email()
                        ->maxLength(255),
                    TextInput::make('contact_phone')
                        ->label('Teléfono de Contacto')
                        ->tel()
                        ->maxLength(30),
                    Toggle::make('active')
                        ->label('Activo')
                        ->default(true),
                ])
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('client_id', null)),

            Select::make('client_id')
                ->label('Deudor')
                ->required()
                ->searchable()
                ->preload()
                ->options(function (Get $get): array {
                    $companyId = $get('company_id');
                    if (! $companyId) {
                        return [];
                    }

                    return Client::where('company_id', $companyId)
                        ->where('active', true)
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->createOptionForm([
                    TextInput::make('name')
                        ->label('Nombre / Razón Social')
                        ->required()
                        ->maxLength(255),
                    Toggle::make('active')
                        ->label('Activo')
                        ->default(true),
                ])
                ->createOptionUsing(function (array $data, Get $get): int {
                    return Client::create([
                        'company_id' => $get('company_id'),
                        'name'       => $data['name'],
                        'active'     => $data['active'] ?? true,
                    ])->id;
                })
                ->helperText('Selecciona primero la compañía'),

            DatePicker::make('request_date')
                ->label('Fecha de Solicitud')
                ->required()
                ->default(now())
                ->displayFormat('d/m/Y')
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                    $days = $get('term_days') ?? 15;
                    if ($state) {
                        $set('due_date', Carbon::parse($state)->addDays((int) $days)->format('Y-m-d'));
                    }
                }),

            TextInput::make('amount')
                ->label('Monto a Financiar')
                ->required()
                ->prefix('RD$')
                ->mask(RawJs::make("\$money(\$input, '.', ',', 2)"))
                ->stripCharacters(',')
                ->numeric()
                ->live(onBlur: true)
                ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace(',', '', $state) : null)
                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                    $amount = (float) str_replace(',', '', $state);
                    $service = new FinancingService();
                    $commission = $service->calculateCommission($amount);
                    $transferAmount = $service->calculateTransferAmount($amount, $commission);
                    $set('commission', number_format($commission, 2, '.', ','));
                    $set('transfer_amount', number_format($transferAmount, 2, '.', ','));
                }),

            TextInput::make('term_days')
                ->label('Plazo (días)')
                ->required()
                ->numeric()
                ->default($defaultTerm)
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                    $date = $get('request_date');
                    if ($date) {
                        $set('due_date', Carbon::parse($date)->addDays((int) $state)->format('Y-m-d'));
                    }
                }),

            TextInput::make('commission')
                ->label('Comisión (5%)')
                ->prefix('RD$')
                ->disabled()
                ->dehydrated()
                ->mask(RawJs::make("\$money(\$input, '.', ',', 2)"))
                ->stripCharacters(',')
                ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace(',', '', $state) : null),

            TextInput::make('transfer_amount')
                ->label('Monto a Transferir')
                ->prefix('RD$')
                ->disabled()
                ->dehydrated()
                ->mask(RawJs::make("\$money(\$input, '.', ',', 2)"))
                ->stripCharacters(',')
                ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace(',', '', $state) : null),

            DatePicker::make('due_date')
                ->label('Fecha de Vencimiento')
                ->disabled()
                ->dehydrated()
                ->displayFormat('d/m/Y')
                ->default(fn () => Carbon::now()->addDays($defaultTerm)->format('Y-m-d')),

            // ── Documentos (OC / Factura) ────────────────────────────────────
            Repeater::make('documents')
                ->label('Documentos (OC / Factura)')
                ->helperText('Al menos un documento es obligatorio.')
                ->relationship('documents')
                ->schema([
                    Select::make('type')
                        ->label('Tipo')
                        ->required()
                        ->options([
                            'purchase_order' => 'Orden de Compra',
                            'invoice'        => 'Factura',
                        ]),

                    TextInput::make('document_number')
                        ->label('Número de Documento')
                        ->maxLength(255)
                        ->nullable(),

                    DatePicker::make('document_date')
                        ->label('Fecha del Documento')
                        ->displayFormat('d/m/Y')
                        ->nullable(),

                    FileUpload::make('file_path')
                        ->label('Adjunto')
                        ->disk('public')
                        ->directory('financing-documents')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(5120)
                        ->openable()
                        ->downloadable()
                        ->nullable(),
                ])
                ->minItems(1)
                ->addActionLabel('Agregar documento')
                ->columnSpanFull(),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Datos del Financiamiento')
                ->columns(3)
                ->schema([
                    TextEntry::make('code')
                        ->label('Código')
                        ->fontFamily('mono')
                        ->copyable(),

                    TextEntry::make('status')
                        ->label('Estado')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'solicited'           => 'warning',
                            'disbursed'           => 'info',
                            'pending_payment'     => 'warning',
                            'partially_collected' => 'primary',
                            'collected'           => 'success',
                            'cancelled'           => 'danger',
                            default               => 'gray',
                        })
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'solicited'           => 'Solicitado',
                            'disbursed'           => 'Desembolsado',
                            'pending_payment'     => 'Pago Pendiente',
                            'partially_collected' => 'Abonado',
                            'collected'           => 'Cobrado',
                            'cancelled'           => 'Cancelado',
                            default               => $state,
                        }),

                    TextEntry::make('company.name')
                        ->label('Compañía'),

                    TextEntry::make('client.name')
                        ->label('Deudor'),

                    TextEntry::make('amount')
                        ->label('Monto')
                        ->money('DOP', locale: 'es_DO'),

                    TextEntry::make('commission')
                        ->label('Comisión')
                        ->money('DOP', locale: 'es_DO'),

                    TextEntry::make('transfer_amount')
                        ->label('Monto a Transferir')
                        ->money('DOP', locale: 'es_DO'),

                    TextEntry::make('collected_amount')
                        ->label('Monto Cobrado')
                        ->money('DOP', locale: 'es_DO')
                        ->visible(fn (Financing $record): bool => (float) $record->collected_amount > 0),

                    TextEntry::make('remaining_balance')
                        ->label('Pendiente de Cobro')
                        ->state(fn (Financing $record): string =>
                            'RD$ ' . number_format($record->remainingBalance(), 2, '.', ',')
                        )
                        ->color('danger')
                        ->weight(\Filament\Support\Enums\FontWeight::Bold)
                        ->visible(fn (Financing $record): bool => $record->status === 'partially_collected'),

                    TextEntry::make('term_days')
                        ->label('Plazo')
                        ->suffix(' días'),

                    TextEntry::make('request_date')
                        ->label('Fecha de Solicitud')
                        ->date('d/m/Y'),

                    TextEntry::make('due_date')
                        ->label('Fecha de Vencimiento')
                        ->date('d/m/Y'),

                    TextEntry::make('issue_period')
                        ->label('Período de Emisión')
                        ->placeholder('—'),

                    TextEntry::make('collection_period')
                        ->label('Período de Cobro')
                        ->placeholder('—'),

                    TextEntry::make('disbursed_at')
                        ->label('Fecha de Desembolso')
                        ->date('d/m/Y')
                        ->placeholder('—'),

                    TextEntry::make('collected_at')
                        ->label('Fecha de Cobro')
                        ->date('d/m/Y')
                        ->placeholder('—'),

                    TextEntry::make('cancellation_reason')
                        ->label('Motivo de Cancelación')
                        ->visible(fn (Financing $record): bool => $record->status === 'cancelled')
                        ->columnSpanFull(),

                    TextEntry::make('registeredBy.name')
                        ->label('Registrado por')
                        ->placeholder('—'),
                ]),

            Section::make('Documentos')
                ->schema([
                    RepeatableEntry::make('documents')
                        ->label('')
                        ->columns(4)
                        ->schema([
                            TextEntry::make('type')
                                ->label('Tipo')
                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                    'purchase_order' => 'Orden de Compra',
                                    'invoice'        => 'Factura',
                                    default          => $state,
                                }),

                            TextEntry::make('document_number')
                                ->label('Número')
                                ->placeholder('—'),

                            TextEntry::make('document_date')
                                ->label('Fecha')
                                ->date('d/m/Y')
                                ->placeholder('—'),

                            TextEntry::make('file_path')
                                ->label('Adjunto')
                                ->url(fn ($state): ?string => $state ? asset('storage/' . $state) : null)
                                ->openUrlInNewTab()
                                ->formatStateUsing(fn ($state): string => $state ? 'Ver archivo' : '—')
                                ->color('primary'),
                        ]),
                ]),

            Section::make('Transacciones Asociadas')
                ->schema([
                    RepeatableEntry::make('transactions')
                        ->label('')
                        ->columns(6)
                        ->schema([
                            TextEntry::make('code')
                                ->label('Código')
                                ->fontFamily('mono')
                                ->url(fn (Transaction $record): string =>
                                    TransactionResource::getUrl('view', ['record' => $record])
                                )
                                ->color('primary')
                                ->weight(\Filament\Support\Enums\FontWeight::SemiBold),

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

                            TextEntry::make('amount')
                                ->label('Monto')
                                ->money('DOP', locale: 'es_DO'),

                            TextEntry::make('transaction_number')
                                ->label('No. Transacción')
                                ->copyable()
                                ->placeholder('—'),

                            TextEntry::make('transaction_date')
                                ->label('Fecha')
                                ->date('d/m/Y'),
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
                    ->label('N° Financiamiento')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->url(fn (Financing $record): string =>
                        static::getUrl('view', ['record' => $record])
                    )
                    ->color('primary')
                    ->toggleable(),

                TextColumn::make('company.name')
                    ->label('Compañía')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('client.name')
                    ->label('Deudor')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('DOP', locale: 'es_DO')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('commission')
                    ->label('Comisión')
                    ->money('DOP', locale: 'es_DO')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('request_date')
                    ->label('Solicitud')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('due_date')
                    ->label('Vencimiento')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'solicited'           => 'warning',
                        'disbursed'           => 'info',
                        'pending_payment'     => 'warning',
                        'partially_collected' => 'primary',
                        'collected'           => 'success',
                        'cancelled'           => 'danger',
                        default               => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'solicited'           => 'Solicitado',
                        'disbursed'           => 'Desembolsado',
                        'pending_payment'     => 'Pago Pendiente',
                        'partially_collected' => 'Abonado',
                        'collected'           => 'Cobrado',
                        'cancelled'           => 'Cancelado',
                        default               => $state,
                    })
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'solicited'           => 'Solicitado',
                        'disbursed'           => 'Desembolsado',
                        'pending_payment'     => 'Pago Pendiente',
                        'partially_collected' => 'Abonado',
                        'collected'           => 'Cobrado',
                        'cancelled'           => 'Cancelado',
                    ]),

                SelectFilter::make('company_id')
                    ->label('Compañía')
                    ->relationship('company', 'name'),
            ])
            ->actions([
            ])
            ->bulkActions([
                BulkAction::make('disburse')
                    ->label('Desembolsar seleccionados')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->visible(fn (): bool => auth()->user()->hasAnyRole(['super_admin', 'operator']))
                    ->action(function (Collection $records): void {
                        $companies = $records->pluck('company_id')->unique();
                        $statuses  = $records->pluck('status')->unique();

                        if ($companies->count() > 1) {
                            Notification::make()
                                ->title('Selección inválida')
                                ->body('Todos los financiamientos deben pertenecer a la misma compañía.')
                                ->danger()
                                ->send();
                            return;
                        }

                        if ($statuses->count() > 1 || $statuses->first() !== 'solicited') {
                            Notification::make()
                                ->title('Selección inválida')
                                ->body('Todos los financiamientos deben estar en estado "Solicitado".')
                                ->danger()
                                ->send();
                            return;
                        }

                        redirect('/admin/transactions/create?' . http_build_query([
                            'type'          => 'disbursement',
                            'company_id'    => $records->first()->company_id,
                            'financing_ids' => $records->pluck('id')->implode(','),
                        ]));
                    }),

                BulkAction::make('collect')
                    ->label(auth()->user()->hasRole('company_user') ? 'Pagar seleccionados' : 'Cobrar seleccionados')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Collection $records): void {
                        $companies = $records->pluck('company_id')->unique();
                        $statuses  = $records->pluck('status')->unique();

                        if ($companies->count() > 1) {
                            Notification::make()
                                ->title('Selección inválida')
                                ->body('Todos los financiamientos deben pertenecer a la misma compañía.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $allowedForCollection = ['disbursed', 'partially_collected'];
                        if ($statuses->diff($allowedForCollection)->isNotEmpty()) {
                            Notification::make()
                                ->title('Selección inválida')
                                ->body('Todos los financiamientos deben estar en estado "Desembolsado" o "Abonado".')
                                ->danger()
                                ->send();
                            return;
                        }

                        redirect('/admin/transactions/create?' . http_build_query([
                            'type'          => 'collection',
                            'company_id'    => $records->first()->company_id,
                            'financing_ids' => $records->pluck('id')->implode(','),
                        ]));
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFinancings::route('/'),
            'create' => Pages\CreateFinancing::route('/create'),
            'view'   => Pages\ViewFinancing::route('/{record}'),
            'edit'   => Pages\EditFinancing::route('/{record}/edit'),
        ];
    }
}
