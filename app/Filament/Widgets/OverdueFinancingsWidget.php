<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\FinancingResource;
use App\Models\Financing;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class OverdueFinancingsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function getHeading(): string
    {
        return auth()->user()->hasRole('company_user')
            ? 'Pagos Vencidos'
            : 'Financiamientos Vencidos';
    }

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'operator', 'company_user']);
    }

    public function table(Table $table): Table
    {
        $user          = auth()->user();
        $isCompanyUser = $user->hasRole('company_user');

        $query = Financing::query()
            ->whereIn('status', ['disbursed', 'partially_collected'])
            ->where('due_date', '<', Carbon::today())
            ->when($isCompanyUser, fn ($q) => $q->where('company_id', $user->company_id))
            ->orderBy('due_date');

        return $table
            ->heading($isCompanyUser ? 'Pagos Vencidos' : 'Financiamientos Vencidos')
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

                TextColumn::make('due_date')
                    ->label('Vencimiento')
                    ->date('d M Y')
                    ->color('danger'),

                TextColumn::make('due_date')
                    ->label('Días Vencido')
                    ->state(fn (Financing $record): int => Carbon::parse($record->due_date)->diffInDays(Carbon::today()))
                    ->suffix(' días')
                    ->color('danger')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'disbursed'           => 'info',
                        'partially_collected' => 'primary',
                        default               => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'disbursed'           => 'Desembolsado',
                        'partially_collected' => 'Abonado',
                        default               => $state,
                    }),
            ])
            ->emptyStateHeading('Sin vencimientos')
            ->emptyStateDescription('No hay financiamientos vencidos.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->searchable(false);
    }
}
