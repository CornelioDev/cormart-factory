<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CuentasPorPagarStatsWidget;
use App\Models\Financing;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class CuentasPorPagarPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon  = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationLabel = 'Cuentas por Pagar';
    protected static ?string $navigationGroup = 'Operaciones';
    protected static ?string $title           = 'Cuentas por Pagar';
    protected static ?int    $navigationSort  = 2;

    protected static string $view = 'filament.pages.cuentas-por-pagar-page';

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'operator']);
    }

    protected function getHeaderWidgets(): array
    {
        return [CuentasPorPagarStatsWidget::class];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Financing::query()
                    ->whereIn('status', ['solicited', 'partially_disbursed'])
                    ->orderBy('request_date')
            )
            ->columns([
                TextColumn::make('code')
                    ->label('N° Financiamiento')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'solicited'           => 'warning',
                        'partially_disbursed' => 'info',
                        default               => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'solicited'           => 'Solicitado',
                        'partially_disbursed' => 'En partidas',
                        default               => $state,
                    }),

                TextColumn::make('company.name')
                    ->label('Compañía')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('client.name')
                    ->label('Deudor')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('amount')
                    ->label('Monto Solicitado')
                    ->money('DOP', locale: 'es_DO')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('commission')
                    ->label('Comisión')
                    ->money('DOP', locale: 'es_DO')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('transfer_amount')
                    ->label('Neto Total')
                    ->money('DOP', locale: 'es_DO')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('pending_disbursement')
                    ->label('Pendiente Desembolso')
                    ->state(fn (Financing $record): float => $record->remainingToDisburse())
                    ->money('DOP', locale: 'es_DO')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('term_days')
                    ->label('Plazo')
                    ->suffix(' días')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('request_date')
                    ->label('Fecha Solicitud')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('due_date')
                    ->label('Vence el')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('days_waiting')
                    ->label('Días en espera')
                    ->state(fn (Financing $record): int =>
                        (int) $record->request_date->diffInDays(now())
                    )
                    ->color(fn (Financing $record): string =>
                        $record->request_date->diffInDays(now()) > 3 ? 'warning' : 'gray'
                    )
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Compañía')
                    ->relationship('company', 'name'),
            ])
            ->bulkActions([
                BulkAction::make('disburse_selected')
                    ->label('Desembolsar seleccionados')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->action(function (Collection $records): void {
                        $companies = $records->pluck('company_id')->unique();

                        if ($companies->count() > 1) {
                            Notification::make()
                                ->title('Selección inválida')
                                ->body('Todos los financiamientos deben pertenecer a la misma compañía.')
                                ->danger()
                                ->send();
                            return;
                        }

                        redirect('/admin/transactions/create?' . http_build_query([
                            'type'          => 'disbursement',
                            'company_id'    => $records->first()->company_id,
                            'financing_ids' => $records->pluck('id')->implode(','),
                        ]));
                    }),
            ])
            ->recordUrl(fn (Financing $record): string => route('filament.admin.resources.financings.view', $record))
            ->emptyStateHeading('Sin solicitudes pendientes')
            ->emptyStateDescription('Todos los financiamientos solicitados han sido procesados.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
