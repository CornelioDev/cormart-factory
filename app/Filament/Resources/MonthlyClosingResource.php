<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MonthlyClosingResource\Pages;
use App\Models\MonthlyClosing;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MonthlyClosingResource extends Resource
{
    protected static ?string $model = MonthlyClosing::class;
    protected static ?string $navigationIcon   = 'heroicon-o-clock';
    protected static ?string $navigationLabel  = 'Historial de Cierres';
    protected static ?string $navigationGroup  = 'Cierre Mensual';
    protected static ?string $modelLabel       = 'Cierre';
    protected static ?string $pluralModelLabel = 'Historial de Cierres';
    protected static ?int    $navigationSort   = 2;
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period')
                    ->label('Periodo')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('total_commissions')
                    ->label('Comisiones')
                    ->money('DOP', locale: 'es_DO')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_fixed')
                    ->label('Fijo Pagado')
                    ->money('DOP', locale: 'es_DO'),
                Tables\Columns\TextColumn::make('reserve')
                    ->label('Reserva')
                    ->money('DOP', locale: 'es_DO'),
                Tables\Columns\TextColumn::make('in_kind_payment')
                    ->label('Naturaleza')
                    ->money('DOP', locale: 'es_DO'),
                Tables\Columns\TextColumn::make('available_for_capital')
                    ->label('Para Capital')
                    ->money('DOP', locale: 'es_DO'),
                Tables\Columns\TextColumn::make('executedBy.name')
                    ->label('Ejecutado por')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('closed_at')
                    ->label('Fecha')
                    ->dateTime('d M Y, H:i')
                    ->color('gray'),
            ])
            ->defaultSort('period', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()->label('Ver detalle'),
            ])
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMonthlyClosings::route('/'),
            'view'  => Pages\ViewMonthlyClosing::route('/{record}'),
        ];
    }
}