<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\FinancingResource;
use App\Models\Financing;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SolicitedFinancingsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'operator', 'company_user']);
    }

    public function table(Table $table): Table
    {
        $user          = auth()->user();
        $isCompanyUser = $user->hasRole('company_user');

        $query = Financing::query()
            ->where('status', 'solicited')
            ->when($isCompanyUser, fn ($q) => $q->where('company_id', $user->company_id))
            ->orderBy('request_date', 'desc');

        return $table
            ->heading('Financiamientos Solicitados')
            ->query($query)
            ->recordUrl(fn (Financing $record) => FinancingResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->fontFamily('mono')
                    ->sortable(),

                TextColumn::make('company.name')
                    ->label('Compañía')
                    ->hidden($isCompanyUser),

                TextColumn::make('client.name')
                    ->label('Deudor'),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('DOP', locale: 'es_DO'),

                TextColumn::make('commission')
                    ->label('Comisión')
                    ->money('DOP', locale: 'es_DO'),

                TextColumn::make('request_date')
                    ->label('Fecha Solicitud')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('term_days')
                    ->label('Plazo')
                    ->suffix(' días'),
            ])
            ->emptyStateHeading('Sin solicitudes pendientes')
            ->emptyStateDescription('No hay financiamientos en espera de desembolso.')
            ->emptyStateIcon('heroicon-o-document-check')
            ->searchable(false);
    }
}
