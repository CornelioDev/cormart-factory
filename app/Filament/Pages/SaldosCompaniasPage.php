<?php

namespace App\Filament\Pages;

use App\Models\Company;
use App\Models\Transaction;
use App\Services\CompanyReceivableService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Saldos que las compañías le deben al fondo.
 *
 * Nacen cuando el fondo entrega efectivo que no corresponde a un financiamiento
 * vigente — por ejemplo un sobre-desembolso. Se saldan cuando la compañía
 * devuelve el dinero.
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

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'operator']);
    }

    protected function service(): CompanyReceivableService
    {
        return app(CompanyReceivableService::class);
    }

    /**
     * Saldos vivos, para el encabezado de la vista.
     */
    public function getSaldosProperty()
    {
        return $this->service()->outstandingByCompany();
    }

    public function getTotalPendienteProperty(): float
    {
        return $this->service()->totalOutstanding();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('registrarDevolucion')
                ->label('Registrar Devolución')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn (): bool => $this->service()->totalOutstanding() > 0.001)
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
                        ->required()
                        ->default(now()),

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
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::query()
                    ->whereIn('type', ['company_receivable', 'company_repayment'])
                    ->where('status', 'confirmed')
                    ->orderByDesc('transaction_date')
                    ->orderByDesc('id')
            )
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('code')
                    ->label('Código')
                    ->fontFamily('mono')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('company.name')
                    ->label('Compañía')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Movimiento')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'company_receivable' ? 'danger' : 'success')
                    ->formatStateUsing(fn (string $state): string => $state === 'company_receivable'
                        ? 'Saldo a favor del fondo'
                        : 'Devolución'),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('DOP')
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
                SelectFilter::make('company_id')
                    ->label('Compañía')
                    ->options(fn (): array => Company::orderBy('name')->pluck('name', 'id')->all()),

                SelectFilter::make('type')
                    ->label('Movimiento')
                    ->options([
                        'company_receivable' => 'Saldo a favor del fondo',
                        'company_repayment'  => 'Devolución',
                    ]),
            ])
            ->emptyStateHeading('Sin movimientos')
            ->emptyStateDescription('Ninguna compañía tiene saldo pendiente con el fondo.');
    }
}
