<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use App\Services\TransactionService;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingTransactionsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Cobros Pendientes de Confirmación';

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'operator']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::query()
                    ->where('status', 'pending')
                    ->where('type', 'collection')
                    ->orderBy('transaction_date')
            )
            ->columns([
                TextColumn::make('company.name')
                    ->label('Compañía')
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('DOP', locale: 'es_DO'),

                TextColumn::make('bank')
                    ->label('Banco'),

                TextColumn::make('transaction_number')
                    ->label('No. Transacción')
                    ->copyable(),

                TextColumn::make('transaction_date')
                    ->label('Fecha')
                    ->date('d M Y'),

                TextColumn::make('financings_count')
                    ->label('Financiamientos')
                    ->counts('financings'),
            ])
            ->actions([
                Action::make('confirm')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar cobro')
                    ->modalDescription('¿Confirmas que este pago fue recibido y verificado?')
                    ->action(function (Transaction $record): void {
                        (new TransactionService())->confirm($record);
                    }),
            ])
            ->emptyStateHeading('Sin cobros pendientes')
            ->emptyStateDescription('Todos los cobros han sido confirmados.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->searchable(false);
    }
}
