<?php

namespace App\Filament\Resources\FundMemberResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClosingDistributionsRelationManager extends RelationManager
{
    protected static string $relationship = 'closingDistributions';

    protected static ?string $title = 'Historial de Distribuciones';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('monthlyClosing.period', 'desc')
            ->columns([
                TextColumn::make('monthlyClosing.period')
                    ->label('Periodo')
                    ->sortable()
                    ->weight('bold')
                    ->toggleable(),

                TextColumn::make('fixed_amount')
                    ->label('Fijo')
                    ->money('DOP', locale: 'es_DO')
                    ->toggleable(),

                TextColumn::make('proportional_amount')
                    ->label('Variable')
                    ->money('DOP', locale: 'es_DO')
                    ->toggleable(),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('DOP', locale: 'es_DO')
                    ->weight('bold')
                    ->color('success')
                    ->toggleable(),
            ]);
    }
}
