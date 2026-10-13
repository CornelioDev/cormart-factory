<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Company;
use App\Models\Financing;
use App\Models\FundMember;
use App\Models\Parameter;
use App\Models\Transaction;
use App\Services\TransactionService;
use Filament\Actions\MountableAction;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section as FormSection;
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
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

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
        // Pre-carga desde query params cuando viene de FinancingResource o de Saldos de Compañías
        $authUser      = auth()->user();
        $isInternal    = $authUser->hasAnyRole(['super_admin', 'operator']);
        $qType         = $isInternal ? request()->query('type') : 'collection';
        $qCompanyId    = $isInternal
            ? (request()->query('company_id') ? (int) request()->query('company_id') : null)
            : $authUser->company_id;
        $qFinancingIds = request()->query('financing_ids')
            ? array_map('intval', explode(',', request()->query('financing_ids')))
            : [];

        // El formulario se lee de arriba abajo como tres preguntas: qué se registra,
        // por cuánto y cómo se paga. El resumen del final lo dice en una frase
        // antes de guardar.
        return $form->schema([
            // ── 1. Qué se registra ───────────────────────────────────────────
            FormSection::make($isInternal ? 'Operación' : 'Financiamientos a pagar')
                ->columns(2)
                ->schema([
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
                            $options['settlement'] = 'Liquidación de cheques (Compañía → Fondo)';
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
                                ? static::formatMoney((new TransactionService())->settleableCredit($get('company_id')))
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
                                    ? static::formatMoney((new TransactionService())->settleableCredit($get('company_id')))
                                    : null);
                            }
                        }),

                    // Proveedor (solo para gastos)
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

                    // Financiamientos vinculados
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
                            $set('credit_applied', null);
                            if ($type === 'collection') {
                                $total = (new TransactionService())->calculateTotalOwed($state);
                                $set('amount', $total > 0 ? number_format($total, 2, '.', ',') : null);
                            } else {
                                $amount = (new TransactionService())->calculateAmount($type ?? '', $state);
                                $set('amount', $amount > 0 ? number_format($amount, 2, '.', ',') : null);
                            }
                        })
                        ->helperText(fn (Get $get): ?string => $isInternal && ! $get('company_id')
                            ? 'Selecciona el tipo y la compañía primero.'
                            : null)
                        ->columnSpanFull(),
                ]),

            // ── 2. Por cuánto ────────────────────────────────────────────────
            FormSection::make('Monto')
                ->columns(2)
                ->schema([
                    // Total adeudado (solo para cobros)
                    Placeholder::make('remaining_balance')
                        ->label('Total adeudado por el deudor')
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

                    // Saldo de cheques que la compañía puede liquidar (solo liquidaciones)
                    Placeholder::make('settleable_credit')
                        ->label('Cheques en manos de la compañía, por liquidar')
                        ->content(fn (Get $get): HtmlString => static::readOnlyMoney(
                            (new TransactionService())->settleableCredit($get('company_id'))
                        ))
                        ->visible(fn (Get $get): bool =>
                            $get('type') === 'settlement' && $get('company_id') !== null
                        ),

                    TextInput::make('amount')
                        ->label(fn (Get $get) => match($get('type')) {
                            'collection'   => auth()->user()->hasRole('company_user') ? 'Monto a Pagar' : 'Monto a Cobrar',
                            'expense'      => 'Monto del Gasto',
                            'settlement'   => 'Monto a Liquidar',
                            'disbursement' => 'Monto a Desembolsar',
                            default        => 'Monto',
                        })
                        ->prefix('RD$')
                        ->required()
                        ->mask(RawJs::make("\$money(\$input, '.', ',', 2)"))
                        ->stripCharacters(',')
                        ->numeric()
                        ->live(onBlur: true)
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
                        ->default(function () use ($qType, $qCompanyId, $qFinancingIds): ?string {
                            if ($qType === 'settlement' && $qCompanyId) {
                                return static::formatMoney((new TransactionService())->settleableCredit($qCompanyId));
                            }
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

                            if ($type === 'settlement') {
                                return 'Puede liquidar el saldo completo o solo una parte.';
                            }

                            if ($type !== 'collection') {
                                return null;
                            }
                            if (count($ids) > 1) {
                                return 'Pago parcial no disponible cuando hay múltiples financiamientos seleccionados';
                            }
                            return 'Puede ingresar un monto parcial (abono) o el total';
                        }),
                ]),

            // ── 3. Cómo se paga ──────────────────────────────────────────────
            FormSection::make('Forma de pago')
                ->description(fn (Get $get): ?string => static::hasApplicableCredit($get)
                    ? 'Esta compañía tiene en mano efectivo de cheques que pertenece al fondo. Puede usarlo para cubrir este desembolso en vez de transferirle dinero.'
                    : null)
                ->columns(2)
                ->schema([
                    // Método de pago del deudor (solo cobros)
                    Select::make('payment_method')
                        ->label('¿Cómo pagó el deudor?')
                        ->required()
                        ->options([
                            'transfer' => 'Transferencia a la cuenta del fondo',
                            'check'    => 'Cheque entregado a la compañía',
                        ])
                        ->default('transfer')
                        ->live()
                        ->visible(fn (Get $get): bool => ($get('type') ?? $qType) === 'collection')
                        ->helperText(fn (Get $get): string => $get('payment_method') === 'check'
                            ? 'El fondo no recibe el dinero todavía: queda en manos de la compañía hasta que lo liquide.'
                            : 'El dinero entra directo a la cuenta bancaria del fondo.')
                        ->columnSpanFull(),

                    // Efectivo de cheques aplicable al desembolso
                    Placeholder::make('available_credit')
                        ->label('Efectivo de cheques en manos de la compañía')
                        ->content(fn (Get $get): HtmlString => static::readOnlyMoney(
                            (new TransactionService())->availableCredit($get('company_id'))
                        ))
                        ->visible(fn (Get $get): bool => static::hasApplicableCredit($get)),

                    TextInput::make('credit_applied')
                        ->label('Cubrir con ese efectivo')
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
                        ->visible(fn (Get $get): bool => static::hasApplicableCredit($get))
                        ->hintAction(
                            FormAction::make('useAllCredit')
                                ->label('Usar todo lo posible')
                                ->icon('heroicon-m-arrow-down-on-square')
                                ->action(function (Get $get, Set $set): void {
                                    $set('credit_applied', static::formatMoney(min(
                                        (new TransactionService())->availableCredit($get('company_id')),
                                        static::toFloat($get('amount'))
                                    )));
                                })
                        )
                        ->helperText('Déjelo vacío para transferir el desembolso completo.'),

                    Placeholder::make('net_transfer')
                        ->label('A transferir desde el banco')
                        ->content(fn (Get $get): HtmlString => static::readOnlyMoney(
                            max(static::netTransfer($get), 0)
                        ))
                        ->visible(fn (Get $get): bool => static::hasApplicableCredit($get)),

                    // Datos bancarios. Solo aparecen si hay movimiento bancario: un
                    // desembolso cubierto al 100% con efectivo de cheques no lo tiene.
                    Select::make('bank')
                        ->label(fn (Get $get): string => $get('payment_method') === 'check'
                            ? 'Banco Emisor del Cheque'
                            : 'Banco')
                        ->required(fn (Get $get): bool => static::hasBankMovement($get))
                        ->visible(fn (Get $get): bool => static::hasBankMovement($get))
                        ->options(Transaction::BANKS),

                    TextInput::make('transaction_number')
                        ->label(fn (Get $get): string => $get('payment_method') === 'check'
                            ? 'Número de Cheque'
                            : 'Número de Transacción')
                        ->required(fn (Get $get): bool => static::hasBankMovement($get))
                        ->visible(fn (Get $get): bool => static::hasBankMovement($get))
                        ->unique(table: 'transactions', column: 'transaction_number')
                        ->maxLength(255),

                    DatePicker::make('transaction_date')
                        ->label('Fecha')
                        ->required()
                        ->default(now())
                        ->displayFormat('d/m/Y'),
                ]),

            // ── Resumen ──────────────────────────────────────────────────────
            FormSection::make('Resumen')
                ->schema([
                    Placeholder::make('summary')
                        ->label('Qué va a pasar al guardar')
                        ->content(fn (Get $get): HtmlString => static::summary($get)),

                    Textarea::make('notes')
                        ->label('Notas')
                        ->required(fn (Get $get): bool => $get('type') === 'expense')
                        ->helperText(fn (Get $get): ?string => $get('type') === 'expense' ? 'Describe el gasto (obligatorio).' : null)
                        ->maxLength(500),
                ]),
        ]);
    }

    /**
     * Monto calculado, con el aspecto de un campo de solo lectura.
     *
     * Es un Placeholder y no un TextInput deshabilitado a propósito: el valor se
     * recalcula en cada render a partir de los demás campos, sin estado propio
     * que haya que mantener sincronizado ni que pueda llegar al guardar.
     */
    protected static function readOnlyMoney(float $value): HtmlString
    {
        return new HtmlString(Blade::render(
            '<x-filament::input.wrapper prefix="RD$" :disabled="true">'
            . '<x-filament::input type="text" :disabled="true" :value="$value" />'
            . '</x-filament::input.wrapper>',
            ['value' => number_format($value, 2, '.', ',')]
        ));
    }

    /**
     * Hay efectivo de cheques que puede cubrir el desembolso que se está registrando.
     */
    protected static function hasApplicableCredit(Get $get): bool
    {
        return $get('type') === 'disbursement'
            && $get('company_id') !== null
            && (new TransactionService())->availableCredit($get('company_id')) > 0;
    }

    /**
     * Describe en lenguaje llano lo que hará la transacción, para leerlo antes de
     * guardar. Solo presenta: los montos salen de los mismos cálculos del servicio.
     */
    protected static function summary(Get $get): HtmlString
    {
        $type   = $get('type');
        $amount = static::toFloat($get('amount'));

        if (! $type || $amount <= 0) {
            return new HtmlString('Complete los datos de arriba para ver aquí el resultado.');
        }

        $service = new TransactionService();
        $money   = fn (float $value): string => 'RD$ ' . number_format($value, 2, '.', ',');
        $who     = e(Company::find($get('company_id'))?->name ?? 'la compañía');
        $lines   = [];

        if ($type === 'disbursement') {
            $credit = min(static::toFloat($get('credit_applied')), $amount);
            $net    = max(round($amount - $credit, 2), 0);

            $lines[] = match (true) {
                $credit > 0 && $net <= 0 => "No sale dinero del banco: los {$money($amount)} se cubren con el efectivo de cheques que {$who} ya tiene en mano.",
                $credit > 0              => "{$who} se queda con {$money($credit)} del efectivo de cheques que ya tiene, y el fondo le transfiere {$money($net)}.",
                default                  => "El fondo transfiere {$money($amount)} a {$who}.",
            };

            if ($credit > 0) {
                $left = max(round($service->settleableCredit($get('company_id')) - $credit, 2), 0);
                $lines[] = $left > 0
                    ? "Después de esto, a {$who} le quedarán {$money($left)} de cheques por liquidar."
                    : "Con esto {$who} queda sin cheques por liquidar.";
            }

            $taxPct = (float) Parameter::where('key', 'tax_pct')->value('value');
            if ($net > 0 && $taxPct > 0) {
                $lines[] = 'El banco cobra ' . $money(round($net * $taxPct / 100, 2))
                    . ' de impuesto por la transferencia (' . Parameter::formatPct($taxPct) . '%), que sale de las ganancias del fondo.';
            }
        } elseif ($type === 'collection') {
            $owed = $service->calculateTotalOwed($get('financing_ids') ?? []);
            $left = max(round($owed - $amount, 2), 0);

            $lines[] = $get('payment_method') === 'check'
                ? "El deudor pagó {$money($amount)} con cheque a {$who}. Los financiamientos se abonan, pero el dinero queda en manos de la compañía hasta que lo liquide o se descuente de un desembolso."
                : "Entran {$money($amount)} a la cuenta del fondo.";

            $lines[] = $left > 0
                ? "Quedarán {$money($left)} por cobrar de estos financiamientos."
                : 'Los financiamientos quedan saldados.';

            if (auth()->user()->hasRole('company_user')) {
                $lines[] = 'El fondo debe confirmar el pago antes de que se aplique.';
            }
        } elseif ($type === 'settlement') {
            $balance = $service->settleableCredit($get('company_id'));
            $left    = max(round($balance - $amount, 2), 0);

            $lines[] = "{$who} transfiere {$money($amount)} al fondo.";
            $lines[] = "Sus cheques por liquidar bajan de {$money($balance)} a {$money($left)}.";
        } elseif ($type === 'expense') {
            $lines[] = "Se registra un gasto de {$money($amount)}, que se descuenta de las ganancias del fondo.";
        }

        return new HtmlString(implode('<br>', $lines));
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
                            'settlement'                => 'primary',
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
                            'company_receivable'        => 'Sobre-desembolso por Devolver',
                            'company_repayment'         => 'Devolución de Sobre-desembolso',
                            'settlement'                => 'Liquidación de Cheques',
                            default                     => $state,
                        }),

                    TextEntry::make('status')
                        ->label('Estado')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'pending'   => 'warning',
                            'confirmed' => 'success',
                            'rejected'  => 'danger',
                            default     => 'gray',
                        })
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'pending'   => 'Pendiente de confirmar',
                            'confirmed' => 'Confirmada',
                            'rejected'  => 'Rechazada',
                            default     => $state,
                        }),

                    TextEntry::make('beneficiario')
                        ->label('Beneficiario')
                        ->getStateUsing(fn (Transaction $record): ?string => $record->getBeneficiario())
                        ->placeholder('—'),

                    TextEntry::make('amount')
                        ->label('Monto Total')
                        ->money('DOP', locale: 'es_DO'),

                    TextEntry::make('payment_method')
                        ->label('Forma de Pago')
                        ->badge()
                        ->color(fn (?string $state): string => $state === 'check' ? 'warning' : 'gray')
                        ->formatStateUsing(fn (?string $state): string => $state === 'check'
                            ? 'Cheque (en manos de la compañía)'
                            : 'Transferencia al fondo')
                        ->visible(fn (Transaction $record): bool => $record->type === 'collection'),

                    TextEntry::make('credit_applied')
                        ->label('Cubierto con Efectivo de Cheques')
                        ->money('DOP', locale: 'es_DO')
                        ->visible(fn (Transaction $record): bool =>
                            $record->type === 'disbursement' && (float) $record->credit_applied > 0
                        ),

                    TextEntry::make('net_transferred')
                        ->label('Transferido desde el Banco')
                        ->getStateUsing(fn (Transaction $record): float => $record->netTransferred())
                        ->money('DOP', locale: 'es_DO')
                        ->visible(fn (Transaction $record): bool =>
                            $record->type === 'disbursement' && (float) $record->credit_applied > 0
                        ),

                    TextEntry::make('bank')
                        ->label('Banco')
                        ->placeholder('—'),

                    TextEntry::make('transaction_number')
                        ->label('No. Transacción')
                        ->placeholder('—')
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
                                    'partially_disbursed'  => 'Desembolso Parcial',
                                    'disbursed'            => 'Desembolsado',
                                    'pending_payment'      => 'Pago por Confirmar',
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
                        'company_receivable'        => 'Sobre-desembolso por Devolver',
                        'company_repayment'         => 'Devolución de Sobre-desembolso',
                        'settlement'                => 'Liquidación de Cheques',
                        default                     => $state,
                    })
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'confirmed' => 'success',
                        'rejected'  => 'danger',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'   => 'Por confirmar',
                        'confirmed' => 'Confirmada',
                        'rejected'  => 'Rechazada',
                        default     => $state,
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
                        'company_receivable'        => 'Sobre-desembolso por Devolver',
                        'company_repayment'         => 'Devolución de Sobre-desembolso',
                        'settlement'                => 'Liquidación de Cheques',
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
                        'pending'   => 'Por confirmar',
                        'confirmed' => 'Confirmada',
                        'rejected'  => 'Rechazada',
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
                static::configureConfirmAction(Action::make('confirm')),
                static::configureCorrectAction(Action::make('correct')),
                static::configureRejectAction(Action::make('reject')),
            ]);
    }

    // ── Acciones sobre un cobro pendiente ──────────────────────────────────
    // Las comparten el listado, la vista del cobro y el widget del dashboard.
    // Cada sitio crea su propia acción (las de tabla y las de página son clases
    // distintas) y aquí se configura igual para todos.

    /**
     * Un cobro pendiente solo lo gestiona personal interno del fondo.
     */
    public static function canManagePending(Transaction $record): bool
    {
        return $record->status === 'pending'
            && $record->type === 'collection'
            && auth()->user()->hasAnyRole(['super_admin', 'operator']);
    }

    public static function configureConfirmAction(MountableAction $action): MountableAction
    {
        return $action
            ->label('Confirmar')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (Transaction $record): bool => static::canManagePending($record))
            ->requiresConfirmation()
            ->modalHeading(fn (Transaction $record): string => "Confirmar cobro {$record->code}")
            ->modalDescription(fn (Transaction $record): string => $record->payment_method === 'check'
                ? 'Cheque por RD$ ' . number_format((float) $record->amount, 2, '.', ',')
                    . '. Al confirmar, los financiamientos se abonan y el dinero queda registrado en manos de la compañía hasta que lo liquide.'
                : 'Transferencia por RD$ ' . number_format((float) $record->amount, 2, '.', ',')
                    . '. Confirme solo si el dinero ya está en la cuenta del fondo: al confirmar se abona a los financiamientos y entra al capital.')
            ->action(function (Transaction $record): void {
                try {
                    (new TransactionService())->confirm($record);
                    $record->refresh();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('No se pudo confirmar la transacción')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    public static function configureCorrectAction(MountableAction $action): MountableAction
    {
        return $action
            ->label('Corregir')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->visible(fn (Transaction $record): bool => static::canManagePending($record))
            ->modalHeading(fn (Transaction $record): string => "Corregir cobro {$record->code}")
            ->modalDescription('El cobro sigue pendiente después de corregirlo. Nada se aplica hasta que lo confirme.')
            ->modalSubmitActionLabel('Guardar corrección')
            ->fillForm(fn (Transaction $record): array => [
                'amount'             => number_format((float) $record->amount, 2, '.', ','),
                'payment_method'     => $record->payment_method,
                'bank'               => $record->bank,
                'transaction_number' => $record->transaction_number,
            ])
            ->form([
                TextInput::make('amount')
                    ->label('Monto recibido')
                    ->prefix('RD$')
                    ->required()
                    ->mask(RawJs::make("\$money(\$input, '.', ',', 2)"))
                    ->stripCharacters(',')
                    ->numeric(),

                Select::make('payment_method')
                    ->label('¿Cómo pagó el deudor?')
                    ->required()
                    ->options([
                        'transfer' => 'Transferencia a la cuenta del fondo',
                        'check'    => 'Cheque entregado a la compañía',
                    ]),

                Select::make('bank')
                    ->label('Banco')
                    ->required()
                    ->options(Transaction::BANKS),

                TextInput::make('transaction_number')
                    ->label('Número de transacción o cheque')
                    ->required()
                    ->maxLength(255),

                Textarea::make('reason')
                    ->label('Motivo de la corrección')
                    ->required()
                    ->maxLength(300)
                    ->helperText('Queda anotado en el cobro junto con su usuario.'),
            ])
            ->action(function (Transaction $record, array $data): void {
                try {
                    (new TransactionService())->correctPendingCollection($record, [
                        'amount'             => static::toFloat($data['amount']),
                        'payment_method'     => $data['payment_method'],
                        'bank'               => $data['bank'],
                        'transaction_number' => $data['transaction_number'],
                    ], $data['reason']);
                    $record->refresh();

                    Notification::make()
                        ->title('Cobro corregido')
                        ->body('Sigue pendiente de confirmación.')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('No se pudo corregir el cobro')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    public static function configureRejectAction(MountableAction $action): MountableAction
    {
        return $action
            ->label('Rechazar')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Transaction $record): bool => static::canManagePending($record))
            ->modalHeading(fn (Transaction $record): string => "Rechazar cobro {$record->code}")
            ->modalDescription('Use esto si el pago no existió o no corresponde. El cobro no se borra: queda como rechazado, y los financiamientos vuelven a estar por cobrar.')
            ->modalSubmitActionLabel('Rechazar cobro')
            ->form([
                Textarea::make('reason')
                    ->label('Motivo del rechazo')
                    ->required()
                    ->maxLength(300),
            ])
            ->action(function (Transaction $record, array $data): void {
                try {
                    (new TransactionService())->rejectPendingCollection($record, $data['reason']);
                    $record->refresh();

                    Notification::make()
                        ->title('Cobro rechazado')
                        ->body('Los financiamientos volvieron a estar por cobrar.')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('No se pudo rechazar el cobro')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
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
