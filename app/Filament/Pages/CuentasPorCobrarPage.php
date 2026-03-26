<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CuentasPorCobrarStatsWidget;
use App\Models\Financing;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CuentasPorCobrarPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon  = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationGroup = 'Operaciones';
    protected static ?int    $navigationSort  = 1;

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->hasRole('company_user')
            ? 'Cuentas por Pagar'
            : 'Cuentas por Cobrar';
    }

    public function getTitle(): string
    {
        return auth()->user()->hasRole('company_user')
            ? 'Cuentas por Pagar'
            : 'Cuentas por Cobrar';
    }

    protected static string $view = 'filament.pages.cuentas-por-cobrar-page';

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'operator', 'company_user']);
    }

    protected function getHeaderWidgets(): array
    {
        return [CuentasPorCobrarStatsWidget::class];
    }

    public function table(Table $table): Table
    {
        $user      = auth()->user();
        $companyId = $user->hasRole('company_user') ? $user->company_id : null;

        $query = Financing::query()
            ->where('status', 'disbursed')
            ->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId))
            ->orderBy('due_date');

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('code')
                    ->label('N° Financiamiento')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono'),

                TextColumn::make('company.name')
                    ->label('Compañía')
                    ->hidden(fn (): bool => auth()->user()->hasRole('company_user'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('client.name')
                    ->label('Deudor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Monto Financiado')
                    ->money('DOP', locale: 'es_DO')
                    ->sortable(),

                TextColumn::make('transfer_amount')
                    ->label('Monto Desembolsado')
                    ->money('DOP', locale: 'es_DO')
                    ->sortable(),

                TextColumn::make('commission')
                    ->label('Comisión')
                    ->money('DOP', locale: 'es_DO')
                    ->sortable(),

                TextColumn::make('disbursed_at')
                    ->label('Fecha Desembolso')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Vencimiento')
                    ->date('d M Y')
                    ->color(fn (Financing $record): string =>
                        $record->due_date->isPast() ? 'danger' : 'success'
                    )
                    ->sortable(),

                TextColumn::make('days_outstanding')
                    ->label('Días en Calle')
                    ->state(fn (Financing $record): int =>
                        (int) $record->disbursed_at?->diffInDays(now())
                    )
                    ->color(fn (Financing $record): string =>
                        $record->due_date->isPast() ? 'danger' : 'gray'
                    ),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Compañía')
                    ->relationship('company', 'name')
                    ->hidden(fn (): bool => auth()->user()->hasRole('company_user')),
            ])
            ->bulkActions([
                BulkAction::make('collect_selected')
                    ->label('Cobrar seleccionados')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
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
                            'type'          => 'collection',
                            'company_id'    => $records->first()->company_id,
                            'financing_ids' => $records->pluck('id')->implode(','),
                        ]));
                    }),
            ])
            ->emptyStateHeading('Sin cuentas por cobrar')
            ->emptyStateDescription('No hay financiamientos desembolsados pendientes de cobro.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
