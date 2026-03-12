<?php

namespace App\Filament\Widgets;

use App\Models\Financing;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ActiveFinancingsWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Financiamientos Desembolsados (Pendientes de Cobro)';

    public static function canView(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $user      = auth()->user();
        $companyId = $user->hasRole('company_user') ? $user->company_id : null;

        $query = Financing::query()
            ->where('status', 'disbursed')
            ->orderBy('due_date');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('company.name')
                    ->label('Compañía')
                    ->hidden(fn (): bool => auth()->user()->hasRole('company_user'))
                    ->searchable(),

                TextColumn::make('client.name')
                    ->label('Deudor')
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('DOP', locale: 'es_DO'),

                TextColumn::make('commission')
                    ->label('Comisión')
                    ->money('DOP', locale: 'es_DO'),

                TextColumn::make('request_date')
                    ->label('Desembolso')
                    ->date('d M Y'),

                TextColumn::make('due_date')
                    ->label('Vencimiento')
                    ->date('d M Y')
                    ->color(fn (Financing $record): string =>
                        $record->due_date->isPast() ? 'danger' : 'success'
                    )
                    ->sortable(),
            ])
            ->emptyStateHeading('Sin financiamientos activos')
            ->emptyStateDescription('No hay financiamientos desembolsados pendientes de cobro.')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->searchable(false);
    }
}
