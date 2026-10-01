<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Company;
use App\Models\Financing;
use App\Models\FundMember;
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
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Support\RawJs;
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
        $query = parent::getEloquentQuery()
            ->with(['company', 'supplier', 'fundMember', 'financings.client']);
        $user  = auth()->user();

        if ($user->hasRole('company_user')) {
            $query->whereIn('type', ['collection', 'settlement'])
                  ->where('company_id', $user->company_id);
        }

        return $query;
    }

    /**
     * Convierte un monto enmascarado del formulario ("20,000.00") a float.
     */
    protected static function toFloat($value): float
    {
        return ($value === null || $value === '')
            ? 0.0
            : (float) str_replace(',', '', (string) $value);
    }

    /**
     * Formatea un monto para los campos enmascarados del formulario.
     */
    protected static function formatMoney(float $amount): ?string
    {
        return $amount > 0 ? number_format($amount, 2, '.', ',') : null;
    }

    /**
     * Monto que realmente sale del banco: el total menos el saldo aplicado.
     */
    protected static function netTransfer(Get $get): float
    {
        return round(static::toFloat($get('amount')) - static::toFloat($get('credit_applied')), 2);
    }

    /**
     * Solo hay movimiento bancario si queda algo por transferir tras aplicar el saldo.
     */
    protected static function hasBankMovement(Get $get): bool
    {
        return $get('type') !== 'disbursement' || static::netTransfer($get) > 0;
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
                    $isSuperAdmin = auth()->user()->hasRole('super_admin');
                    $options = [
                        'disbursement' => 'Desembolso (Fondo → Compañía)',
                        'collection'   => 'Cobro (Deudor → Fondo)',
                    ];
                    $options['settlement'] = 'Liquidación de saldo (Compañía → Fondo)';
                    if ($isSuperAdmin) {
                        $options['expense'] = 'Gasto Operativo';
                    }
                    return $options;
                })
                ->default($qType)
                ->hidden(! $isInternal)
                ->dehydrated()
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set) {
                    $set('financing_ids', []);
                    $set('credit_applied', null);
                    $set('payment_method', 'transfer');
                    $set('amount', $get('type') === 'settlement'
                        ? static::formatMoney((new TransactionService())->availableCredit($get('company_id')))
                        : null);
                }),

            Select::make('company_id')
                ->label('Compañía')
                ->searchable()
                ->preload()
                ->relationship('company', 'name', fn ($query) => $query->where('active', true))
                ->default($qCompanyId)
                ->hidden(! $isInternal)
                ->dehydrated()
                ->live()
                ->visible(fn (Get $get): bool => $get('type') !== 'expense')
                ->afterStateUpdated(function (Get $get, Set $set) use ($isInternal) {
                    if ($isInternal) {
                        $set('financing_ids', []);
                        $set('credit_applied', null);
                        $set('amount', $get('type') === 'settlement'
                            ? static::formatMoney((new TransactionService())->availableCredit($get('company_id')))
                            : null);
                    }
                }),

            // ── Proveedor (solo para gastos) ─────────────────────────────────
            Select::make('supplier_id')
                ->label('Proveedor')
                ->relationship('supplier', 'name')
                ->searchable()
                ->preload()
                ->createOptionForm([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('rnc')
                        ->label('RNC')
                        ->maxLength(50),
                ])
                ->required(fn (Get $get): bool => $get('type') === 'expense')
                ->visible(fn (Get $get): bool => $get('type') === 'expense'),

            // ── Financiamientos vinculados ───────────────────────────────────
            Select::make('financing_ids')
                ->label('Financiamientos')
                ->multiple()
                ->required(fn (Get $get): bool => ! in_array($get('type'), ['expense', 'settlement'], true))
                ->default($qFinancingIds)
                ->live()
                ->visible(fn (Get $get): bool => ! in_array($get('type'), ['expense', 'settlement'], true))
                ->options(function (Get $get) use ($qType, $qCompanyId, $qFinancingIds): array {
                    $type      = $get('type') ?? $qType;
                    $companyId = $get('company_id') ?? $qCompanyId;

                    if (! $type || ! $companyId) {
                        return [];
                    }

                    $statuses = $type === 'disbursement'
                        ? ['solicited', 'partially_disbursed']
                        : ['partially_disbursed', 'disbursed', 'partially_collected'];

                    $query = Financing::query()
                        ->where('company_id', $companyId)
                        ->where(function ($q) use ($statuses, $qFinancingIds) {
                            $q->whereIn('status', $statuses);
                            if (! empty($qFinancingIds)) {
                                $q->orWhereIn('id', $qFinancingIds);
                            }
                        });

                    return $query->with('client')->get()
                        ->mapWithKeys(function (Financing $f) use ($type): array {
                            $code = $f->code ?? ('FN' . str_pad($f->id, 6, '0', STR_PAD_LEFT));
                            $label = sprintf('%s — %s — RD$ %s', $code, $f->client->name, number_format($f->amount, 2, '.', ','));

                            if ($type === 'disbursement' && (float) $f->disbursed_amount > 0) {
                                $label .= ' (Pend. desembolso: RD$ ' . number_format($f->remainingToDisburse(), 2, '.', ',') . ')';
                            } elseif ($type === 'collection' && (float) $f->collected_amount > 0) {
                                $label .= ' (Pendiente: RD$ ' . number_format($f->remainingBalance(), 2, '.', ',') . ')';
                            }

                            return [$f->id => $label];
                        })
                        ->toArray();
                })
                ->afterStateUpdated(function (Get $get, Set $set, array $state): void {
                    $type = $get('type');
                    if ($type === 'collection') {
                        $total = (new TransactionService())->calculateTotalOwed($state);
                        $set('amount', $total > 0 ? number_format($total, 2, '.', ',') : null);
                    } else {
                        $amount = (new TransactionService())->calculateAmount($type ?? '', $state);
                        $set('amount', $amount > 0 ? number_format($amount, 2, '.', ',') : null);
                    }
                })
                ->helperText('Selecciona el tipo y la compañía primero')
                ->columnSpanFull(),

            // ── Balance pendiente (solo para cobros) ───────────────────────
            Placeholder::make('remaining_balance')
                ->label('Total Adeudado')
                ->content(function (Get $get): string {
                    $ids = $get('financing_ids') ?? [];
                    if (empty($ids) || $get('type') !== 'collection') {
                        return '—';
                    }
                    $service   = new TransactionService();
                    $capital   = $service->calculateRemainingBalance($ids);
                    $lateFee   = $service->calculateTotalOwed($ids) - $capital;
                    $total     = $capital + $lateFee;

                    if ($lateFee > 0) {
                        return 'RD$ ' . number_format($total, 2, '.', ',')
                            . ' (Capital: RD$ ' . number_format($capital, 2, '.', ',')
                            . ' + Mora: RD$ ' . number_format($lateFee, 2, '.', ',') . ')';
                    }

                    return 'RD$ ' . number_format($capital, 2, '.', ',');
                })
                ->visible(fn (Get $get): bool => $get('type') === 'collection'),

            // ── Monto ──────────────────────────────────────────────────────
            // ── Método de pago del deudor ────────────────────────────────────
            Select::make('payment_method')
                ->label('Método de Pago')
                ->required()
                ->options([
                    'transfer' => 'Transferencia (a la cuenta del fondo)',
                    'check'    => 'Cheque (queda en manos de la compañía)',
                ])
                ->default('transfer')
                ->live()
                ->visible(fn (Get $get): bool => ($get('type') ?? $qType) === 'collection')
                ->helperText(fn (Get $get): ?string => $get('payment_method') === 'check'
                    ? 'El dinero queda en manos de la compañía y genera saldo a favor del fondo.'
                    : null),

            // ── Saldo a favor del fondo en manos de la compañía ─────────────
            Placeholder::make('available_credit')
                ->label('Saldo aplicable de la compañía')
                ->content(fn (Get $get): string => 'RD$ ' . number_format(
                    (new TransactionService())->availableCredit($get('company_id')),
                    2, '.', ','
                ))
                ->visible(fn (Get $get): bool =>
                    in_array($get('type'), ['disbursement', 'settlement'], true)
                    && $get('company_id') !== null
                ),

            TextInput::make('amount')
                ->label(fn (Get $get) => match($get('type')) {
                    'collection' => auth()->user()->hasRole('company_user') ? 'Monto a Pagar' : 'Monto a Cobrar',
                    'expense'    => 'Monto del Gasto',
                    'settlement' => 'Monto a Liquidar',
                    default      => 'Monto Total',
                })
                ->prefix('RD$')
                ->required()
                ->mask(RawJs::make("\$money(\$input, '.', ',', 2)"))
                ->stripCharacters(',')
                ->numeric()
                ->disabled(function (Get $get): bool {
                    $type = $get('type');
                    $count = count($get('financing_ids') ?? []);
                    if ($type === 'disbursement') {
                        return $count !== 1;
                    }
                    if ($type === 'collection') {
                        return $count > 1;
                    }
                    return false;
                })
                ->dehydrated()
                ->default(function () use ($qType, $qFinancingIds): ?string {
                    if (! $qType || empty($qFinancingIds)) {
                        return null;
                    }
                    if ($qType === 'collection') {
                        $amount = (new TransactionService())->calculateTotalOwed($qFinancingIds);
                    } else {
                        $amount = (new TransactionService())->calculateAmount($qType, $qFinancingIds);
                    }
                    return $amount > 0 ? number_format($amount, 2, '.', ',') : null;
                })
                ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace(',', '', $state) : null)
                ->rules([
                    fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                        if ($get('type') !== 'disbursement') {
                            return;
                        }
                        $ids = $get('financing_ids') ?? [];
                        if (count($ids) !== 1) {
                            return;
                        }
                        $financing = Financing::find($ids[0]);
                        if (! $financing) {
                            return;
                        }
                        $amount = $value ? (float) str_replace(',', '', $value) : 0;
                        if ($amount > $financing->remainingToDisburse() + 0.001) {
                            $fail('El monto de la partida no puede superar el pendiente por desembolsar (RD$ '
                                . number_format($financing->remainingToDisburse(), 2, '.', ',') . ').');
                        }
                    },
                ])
                ->helperText(function (Get $get): ?string {
                    $type = $get('type');
                    $ids = $get('financing_ids') ?? [];

                    if ($type === 'disbursement') {
                        if (count($ids) === 1) {
                            return 'Puede ingresar un monto parcial (partida) o el total pendiente.';
                        }
                        if (count($ids) > 1) {
                            return 'Con varios financiamientos seleccionados se desembolsa el pendiente completo de cada uno.';
                        }
                        return null;
                    }

                    if ($type !== 'collection') {
                        return null;
                    }
                    if (count($ids) > 1) {
                        return 'Pago parcial no disponible cuando hay múltiples financiamientos seleccionados';
                    }
                    return 'Puede ingresar un monto parcial (abono) o el total';
                }),

            // ── Saldo aplicado al desembolso ─────────────────────────────────
            TextInput::make('credit_applied')
                ->label('Saldo a Aplicar')
                ->prefix('RD$')
                ->mask(RawJs::make("\$money(\$input, '.', ',', 2)"))
                ->stripCharacters(',')
                ->numeric()
                ->live(onBlur: true)
                ->default(null)
                ->minValue(0)
                ->maxValue(fn (Get $get): float => min(
                    (new TransactionService())->availableCredit($get('company_id')),
                    static::toFloat($get('amount'))
                ))
                ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace(',', '', $state) : 0)
                ->visible(fn (Get $get): bool =>
                    $get('type') === 'disbursement'
                    && (new TransactionService())->availableCredit($get('company_id')) > 0
                )
                ->helperText(fn (Get $get): string =>
                    'Transferencia real: RD$ ' . number_format(max(static::netTransfer($get), 0), 2, '.', ',')
                ),

            // ── Datos bancarios ──────────────────────────────────────────────
            // Banco y número dejan de ser obligatorios cuando no hay transferencia:
            // un desembolso cubierto al 100% con saldo no mueve el banco.
            Select::make('bank')
                ->label(fn (Get $get): string => $get('payment_method') === 'check'
                    ? 'Banco Emisor del Cheque'
                    : 'Banco')
                ->required(fn (Get $get): bool => static::hasBankMovement($get))
                ->options(Transaction::BANKS),

            TextInput::make('transaction_number')
                ->label(fn (Get $get): string => $get('payment_method') === 'check'
                    ? 'Número de Cheque'
                    : 'Número de Transacción')
                ->required(fn (Get $get): bool => static::hasBankMovement($get))
                ->unique(table: 'transactions', column: 'transaction_number')
                ->maxLength(255),

            DatePicker::make('transaction_date')
                ->label('Fecha de Transacción')
                ->required()
                ->default(now())
                ->displayFormat('d/m/Y'),

            Textarea::make('notes')
                ->label('Notas')
                ->required(fn (Get $get): bool => $get('type') === 'expense')
                ->helperText(fn (Get $get): ?string => $get('type') === 'expense' ? 'Describe el gasto (obligatorio).' : null)
                ->maxLength(500)
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
                            'disbursement'              => 'info',
                            'collection'                => 'success',
                            'expense'                   => 'danger',
                            'earning_distribution'      => 'success',
                            'member_disbursement'       => 'warning',
                            'fund_loan_to_capital'      => 'warning',
                            'capital_repayment_to_fund' => 'primary',
                            'company_receivable'        => 'danger',
                            'company_repayment'         => 'success',
                            default                     => 'gray',
                        })
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'disbursement'              => 'Desembolso',
                            'collection'                => 'Cobro',
                            'expense'                   => 'Gasto',
                            'earning_distribution'      => 'Distribución',
                            'member_disbursement'       => 'Desembolso a Miembro',
                            'fund_loan_to_capital'      => 'Préstamo Interno (Fondo→Capital)',
                            'capital_repayment_to_fund' => 'Repago al Fondo',
                            'company_receivable'        => 'Saldo a Favor del Fondo',
                            'company_repayment'         => 'Devolución de Compañía',
                            default                     => $state,
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

                    TextEntry::make('beneficiario')
                        ->label('Beneficiario')
                        ->getStateUsing(fn (Transaction $record): ?string => $record->getBeneficiario())
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
                ->visible(fn ($record): bool => ! in_array($record->type, ['expense', 'earning_distribution', 'member_disbursement']))
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
                                ->money('DOP', locale: 'es_DO')
                                ->visible(function (TextEntry $component): bool {
                                    $transaction = $component->getLivewire()->record;
                                    return $transaction->type === 'disbursement';
                                }),

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
                    ->color('primary')
                    ->toggleable(),

                TextColumn::make('beneficiario')
                    ->label('Beneficiario')
                    ->getStateUsing(fn (Transaction $record): ?string => $record->getBeneficiario())
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->whereHas('company', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                              ->orWhereHas('supplier', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                              ->orWhereHas('fundMember', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                        });
                    })
                    ->tooltip(function (Transaction $record): ?string {
                        if ($record->type === 'expense') {
                            return null;
                        }
                        $names = $record->financings->pluck('client.name')->filter()->join(', ');
                        return $names ?: null;
                    })
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('DOP', locale: 'es_DO')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('bank')
                    ->label('Banco')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('transaction_number')
                    ->label('No. Transacción')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('transaction_date')
                    ->label('Fecha')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'disbursement'              => 'info',
                        'collection'                => 'success',
                        'expense'                   => 'danger',
                        'earning_distribution'      => 'success',
                        'member_disbursement'       => 'warning',
                        'fund_loan_to_capital'      => 'warning',
                        'capital_repayment_to_fund' => 'primary',
                        'company_receivable'        => 'danger',
                        'company_repayment'         => 'success',
                        'settlement'                => 'primary',
                        default                     => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'disbursement'              => 'Desembolso',
                        'collection'                => 'Cobro',
                        'expense'                   => 'Gasto',
                        'earning_distribution'      => 'Distribución',
                        'member_disbursement'       => 'Desembolso a Miembro',
                        'fund_loan_to_capital'      => 'Préstamo Interno',
                        'capital_repayment_to_fund' => 'Repago al Fondo',
                        'company_receivable'        => 'Saldo a Favor del Fondo',
                        'company_repayment'         => 'Devolución de Compañía',
                        'settlement'                => 'Liquidación de Saldo',
                        default                     => $state,
                    })
                    ->toggleable(),

                TextColumn::make('payment_method')
                    ->label('Método')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'check' ? 'warning' : 'gray')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'check' => 'Cheque',
                        default => 'Transferencia',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'disbursement'              => 'Desembolso',
                        'collection'                => 'Cobro',
                        'expense'                   => 'Gasto',
                        'earning_distribution'      => 'Distribución',
                        'member_disbursement'       => 'Desembolso a Miembro',
                        'fund_loan_to_capital'      => 'Préstamo Interno (Fondo→Capital)',
                        'capital_repayment_to_fund' => 'Repago al Fondo',
                        'company_receivable'        => 'Saldo a Favor del Fondo',
                        'company_repayment'         => 'Devolución de Compañía',
                        'settlement'                => 'Liquidación de Saldo',
                    ]),

                SelectFilter::make('payment_method')
                    ->label('Método de Pago')
                    ->options([
                        'transfer' => 'Transferencia',
                        'check'    => 'Cheque',
                    ]),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending'   => 'Pendiente',
                        'confirmed' => 'Confirmada',
                    ]),

                SelectFilter::make('bank')
                    ->label('Banco')
                    ->options(Transaction::BANKS),

                SelectFilter::make('beneficiario')
                    ->label('Beneficiario')
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (! $value) {
                            return $query;
                        }

                        [$type, $id] = explode('_', $value, 2);

                        return $query->where(match ($type) {
                            'company'  => 'company_id',
                            'supplier' => 'supplier_id',
                            'member'   => 'fund_member_id',
                        }, (int) $id);
                    })
                    ->options(function (): array {
                        $companies = \App\Models\Company::orderBy('name')
                            ->pluck('name', 'id')
                            ->mapWithKeys(fn ($name, $id) => ["company_{$id}" => $name])
                            ->toArray();

                        $suppliers = \App\Models\Supplier::orderBy('name')
                            ->pluck('name', 'id')
                            ->mapWithKeys(fn ($name, $id) => ["supplier_{$id}" => $name])
                            ->toArray();

                        $members = FundMember::orderBy('name')
                            ->pluck('name', 'id')
                            ->mapWithKeys(fn ($name, $id) => ["member_{$id}" => $name])
                            ->toArray();

                        return $companies + $suppliers + $members;
                    })
                    ->searchable(),
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
                        try {
                            (new TransactionService())->confirm($record);
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('No se pudo confirmar la transacción')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
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
