<?php

namespace App\Filament\Resources\FinancingResource\Pages;

use App\Filament\Resources\FinancingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFinancing extends ViewRecord
{
    protected static string $resource = FinancingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn (): bool => $this->record->status === 'solicited'),

            Actions\Action::make('disburse')
                ->label('Desembolsar')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->visible(fn (): bool => $this->record->status === 'solicited'
                    && auth()->user()->hasAnyRole(['super_admin', 'operator']))
                ->url(fn (): string => '/admin/transactions/create?' . http_build_query([
                    'type'          => 'disbursement',
                    'company_id'    => $this->record->company_id,
                    'financing_ids' => (string) $this->record->id,
                ])),

            Actions\Action::make('collect')
                ->label('Cobrar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => in_array($this->record->status, ['disbursed', 'partially_collected']))
                ->url(fn (): string => '/admin/transactions/create?' . http_build_query([
                    'type'          => 'collection',
                    'company_id'    => $this->record->company_id,
                    'financing_ids' => (string) $this->record->id,
                ])),
        ];
    }
}
