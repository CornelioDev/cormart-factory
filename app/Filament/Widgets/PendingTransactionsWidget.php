<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionService;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingTransactionsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function getHeading(): string
    {
        return auth()->user()->hasRole('company_user')
            ? 'Pagos Pendientes de Confirmación'
            : 'Cobros Pendientes de Confirmación';
    }

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'operator', 'company_user']);
    }

    public function table(Table $table): Table
    {
        $user      = auth()->user();
        $isCompanyUser = $user->hasRole('company_user');

        $query = Transaction::query()
            ->where('status', 'pending')
            ->where('type', 'collection')
            ->when($isCompanyUser, fn ($q) => $q->where('company_id', $user->company_id))
            ->orderBy('transaction_date');

        return $table
            ->heading($isCompanyUser ? 'Pagos Pendientes de Confirmación' : 'Cobros Pendientes de Confirmación')
            ->query($query)
            ->recordUrl(fn (Transaction $record) => TransactionResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->fontFamily('mono')
                    ->sortable(),

                TextColumn::make('company.name')
                    ->label('Compañía')
                    ->searchable()
                    ->hidden($isCompanyUser),

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
                    ->visible(! $isCompanyUser)
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar cobro')
                    ->modalDescription('¿Confirmas que este pago fue recibido y verificado?')
                    ->action(function (Transaction $record): void {
                        (new TransactionService())->confirm($record);
                    }),
            ])
            ->emptyStateHeading($isCompanyUser ? 'Sin pagos pendientes' : 'Sin cobros pendientes')
            ->emptyStateDescription($isCompanyUser ? 'Todos los pagos han sido procesados.' : 'Todos los cobros han sido confirmados.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->searchable(false);
    }
}
