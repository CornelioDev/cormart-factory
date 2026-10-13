<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TransactionResource;
use App\Models\CompanyCredit;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Historial del libro de cheques en manos de compañías. Se muestra dentro de
 * Saldos de Compañías, no en el dashboard.
 */
class CompanyCreditMovementsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int | string | array $columnSpan = 'full';

    /**
     * Compañía a la que se acota el historial; la fija la página. Null = todas.
     */
    public ?int $companyId = null;

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'operator']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => CompanyCredit::query()
                ->with(['company', 'transaction'])
                ->when($this->companyId, fn ($query) => $query->where('company_id', $this->companyId))
                ->orderByDesc('id'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->date('d/m/Y'),

                TextColumn::make('company.name')
                    ->label('Compañía')
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Movimiento')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'accrual'     => 'warning',
                        'application' => 'info',
                        default       => 'success',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'accrual'     => 'Cheque recibido por la compañía',
                        'application' => 'Descontado de un desembolso',
                        'settlement'  => 'Liquidado por transferencia',
                        default       => $state,
                    }),

                TextColumn::make('transaction.code')
                    ->label('Transacción')
                    ->fontFamily('mono')
                    ->color('primary')
                    ->url(fn (CompanyCredit $record): ?string => $record->transaction
                        ? TransactionResource::getUrl('view', ['record' => $record->transaction])
                        : null),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('DOP', locale: 'es_DO')
                    ->color(fn (CompanyCredit $record): string => (float) $record->amount >= 0 ? 'warning' : 'success')
                    ->alignEnd(),

                TextColumn::make('late_fee_amount')
                    ->label('De ello, mora')
                    ->money('DOP', locale: 'es_DO')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('notes')
                    ->label('Notas')
                    ->wrap()
                    ->limit(90)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Movimiento')
                    ->options([
                        'accrual'     => 'Cheque recibido por la compañía',
                        'application' => 'Descontado de un desembolso',
                        'settlement'  => 'Liquidado por transferencia',
                    ]),
            ])
            ->emptyStateHeading('Sin movimientos')
            ->emptyStateDescription('Ningún deudor ha pagado con cheque a esta selección de compañías.')
            ->paginated([10, 25, 50]);
    }
}
