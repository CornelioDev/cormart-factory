<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TransactionResource;
use App\Models\Company;
use App\Models\Transaction;
use App\Services\CompanyCreditService;
use App\Services\CompanyReceivableService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

/**
 * Todo lo que las compañías le deben al fondo, en un solo lugar y sin mezclar.
 *
 * Son dos deudas distintas, con origen y forma de saldarse distintos:
 *
 *   Cheques en manos de la compañía (CompanyCreditService)
 *     Un deudor pagó su factura con cheque a la compañía. El financiamiento quedó
 *     abonado, pero el dinero no ha llegado al fondo. Se salda con una
 *     liquidación (transferencia) o descontándolo de un desembolso.
 *
 *   Sobre-desembolsos (CompanyReceivableService)
 *     El fondo le transfirió a la compañía más de lo que correspondía. Se salda
 *     únicamente cuando la compañía devuelve el dinero.
 *
 * La página es una tabla con una fila por compañía y una columna por deuda, para
 * que crezca hacia abajo y no en bloques por compañía. El historial va debajo,
 * una pestaña por deuda, y se puede acotar a la compañía elegida en la tabla.
 * Nunca se suman en un saldo único aplicable: sus reglas no son las mismas.
 */
class SaldosCompaniasPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon  = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Saldos de Compañías';
    protected static ?string $navigationGroup = 'Operaciones';
    protected static ?string $title           = 'Saldos de Compañías';
    protected static ?int    $navigationSort  = 4;

    protected static string $view = 'filament.pages.saldos-companias-page';

    /**
     * Compañía cuyos movimientos se están viendo. Null = todas.
     */
    public ?int $companyId = null;

    /**
     * Historial visible: 'checks' o 'overdisbursed'.
     */
    public string $tab = 'checks';

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'operator']);
    }

    protected function service(): CompanyReceivableService
    {
        return app(CompanyReceivableService::class);
    }

    /**
     * Una fila por compañía que debe algo, con cada deuda por separado.
     *
     * @return Collection<int, array{company: Company, checks: float, checks_late_fee: float, overdisbursed: float, total: float}>
     */
    public function getResumenProperty(): Collection
    {
        $credits       = app(CompanyCreditService::class);
        $checks        = $credits->outstandingByCompany();
        $overdisbursed = $this->service()->outstandingByCompany()
            ->mapWithKeys(fn (array $row) => [$row['company']->id => $row['balance']]);

        $ids = $checks->keys()->merge($overdisbursed->keys())->unique();

        return Company::whereIn('id', $ids)->orderBy('name')->get()
            ->map(function (Company $company) use ($credits, $checks, $overdisbursed): array {
                $checkBalance = (float) ($checks[$company->id] ?? 0);
                $overBalance  = (float) ($overdisbursed[$company->id] ?? 0);

                return [
                    'company'         => $company,
                    'checks'          => $checkBalance,
                    'checks_late_fee' => $checkBalance > 0 ? $credits->compositionFor($company->id)['late_fee'] : 0.0,
                    'overdisbursed'   => $overBalance,
                    'total'           => round($checkBalance + $overBalance, 2),
                ];
            })
            ->values();
    }

    /**
     * Acota los dos historiales a una compañía y, si se indica, cambia de pestaña.
     * Pasar null vuelve a mostrar todas.
     */
    public function verMovimientos(?int $companyId, ?string $tab = null): void
    {
        $this->companyId = $companyId;

        if (in_array($tab, ['checks', 'overdisbursed'], true)) {
            $this->tab = $tab;
        }

        $this->resetPage();
    }

    public function getSelectedCompanyProperty(): ?Company
    {
        return $this->companyId ? Company::find($this->companyId) : null;
    }

    public function liquidacionUrl(int $companyId): string
    {
        return TransactionResource::getUrl('create', ['type' => 'settlement', 'company_id' => $companyId]);
    }

    public function desembolsoUrl(int $companyId): string
    {
        return TransactionResource::getUrl('create', ['type' => 'disbursement', 'company_id' => $companyId]);
    }

    /**
     * Devolución de un sobre-desembolso. Se lanza desde la fila de la compañía,
     * que llega ya elegida y con su saldo como monto.
     */
    public function registrarDevolucionAction(): Action
    {
        return Action::make('registrarDevolucion')
            ->label('Registrar devolución')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->size('xs')
            ->modalHeading('Registrar devolución de sobre-desembolso')
            ->modalDescription('La compañía devuelve al fondo el dinero que recibió de más. El monto entra a la cuenta de capital.')
            ->fillForm(fn (array $arguments): array => [
                'company_id'       => $arguments['company'] ?? null,
                'amount'           => isset($arguments['company'])
                    ? $this->service()->balanceFor((int) $arguments['company'])
                    : null,
                'transaction_date' => now(),
            ])
            ->form([
                Select::make('company_id')
                    ->label('Compañía')
                    ->required()
                    ->live()
                    ->options(fn (): array => $this->service()->outstandingByCompany()
                        ->mapWithKeys(fn (array $row) => [
                            $row['company']->id => $row['company']->name
                                . ' — RD$ ' . number_format($row['balance'], 2, '.', ','),
                        ])
                        ->all()),

                TextInput::make('amount')
                    ->label('Monto devuelto (RD$)')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->helperText(fn (callable $get): string => $get('company_id')
                        ? 'Saldo pendiente: RD$ ' . number_format(
                            $this->service()->balanceFor((int) $get('company_id')), 2, '.', ','
                        )
                        : 'Seleccione una compañía para ver su saldo.'),

                DatePicker::make('transaction_date')
                    ->label('Fecha')
                    ->required(),

                Select::make('bank')
                    ->label('Banco')
                    ->options(Transaction::BANKS),

                TextInput::make('transaction_number')
                    ->label('N° de transacción')
                    ->maxLength(255),

                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(2),
            ])
            ->action(function (array $data): void {
                try {
                    $this->service()->repay($data);
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('No se pudo registrar la devolución')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Devolución registrada')
                    ->body('El monto se acreditó a la cuenta de capital.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Historial de sobre-desembolsos y sus devoluciones (pestaña 'overdisbursed').
     */
    public function table(Table $table): Table
    {
        return $table
            // Closure, no Builder: la tabla se arma al arrancar la petición, antes de
            // que verMovimientos() cambie la compañía. Evaluada tarde, ya la ve.
            ->query(fn () => Transaction::query()
                ->whereIn('type', ['company_receivable', 'company_repayment'])
                ->where('status', 'confirmed')
                ->when($this->companyId, fn ($query) => $query->where('company_id', $this->companyId))
                ->orderByDesc('transaction_date')
                ->orderByDesc('id'))
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('code')
                    ->label('Transacción')
                    ->fontFamily('mono')
                    ->searchable()
                    ->color('primary')
                    ->url(fn (Transaction $record): string => TransactionResource::getUrl('view', ['record' => $record])),

                TextColumn::make('company.name')
                    ->label('Compañía')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Movimiento')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'company_receivable' ? 'danger' : 'success')
                    ->formatStateUsing(fn (string $state): string => $state === 'company_receivable'
                        ? 'Sobre-desembolso'
                        : 'Devolución'),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('DOP', locale: 'es_DO')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('notes')
                    ->label('Notas')
                    ->wrap()
                    ->limit(90)
                    ->toggleable(),

                TextColumn::make('registeredBy.name')
                    ->label('Registrado por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Movimiento')
                    ->options([
                        'company_receivable' => 'Sobre-desembolso',
                        'company_repayment'  => 'Devolución',
                    ]),
            ])
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Sin movimientos')
            ->emptyStateDescription('El fondo no le ha transferido de más a esta selección de compañías.');
    }
}
