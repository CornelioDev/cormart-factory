<?php

namespace App\Filament\Resources\FundMemberResource\RelationManagers;

use App\Models\Transaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EarningsTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'earningDistributions';

    protected static ?string $title = 'Transacciones de Ganancias';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) =>
                Transaction::query()
                    ->where('fund_member_id', $this->getOwnerRecord()->id)
                    ->whereIn('type', ['earning_distribution', 'member_disbursement', 'earnings_to_capital'])
            )
            ->defaultSort('transaction_date', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->fontFamily('mono')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('transaction_date')
                    ->label('Fecha')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'earning_distribution' => 'success',
                        'member_disbursement'  => 'warning',
                        'earnings_to_capital'  => 'primary',
                        default                => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'earning_distribution' => 'Distribución',
                        'member_disbursement'  => 'Desembolso',
                        'earnings_to_capital'  => 'Capitalización',
                        default                => $state,
                    })
                    ->toggleable(),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('DOP', locale: 'es_DO')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('notes')
                    ->label('Notas')
                    ->limit(50)
                    ->tooltip(fn (Transaction $record): ?string => $record->notes)
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'success',
                        'pending'   => 'warning',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'confirmed' => 'Confirmada',
                        'pending'   => 'Pendiente',
                        default     => $state,
                    })
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'earning_distribution' => 'Distribución',
                        'member_disbursement'  => 'Desembolso',
                        'earnings_to_capital'  => 'Capitalización',
                    ]),
            ]);
    }
}
