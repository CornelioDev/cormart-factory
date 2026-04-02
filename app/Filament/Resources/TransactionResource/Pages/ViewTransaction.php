<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Services\TransactionService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirm')
                ->label('Confirmar cobro')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () =>
                    $this->record->status === 'pending'
                    && $this->record->type === 'collection'
                    && auth()->user()->hasAnyRole(['super_admin', 'operator'])
                )
                ->requiresConfirmation()
                ->modalHeading('Confirmar cobro')
                ->modalDescription('¿Confirmas que este pago fue recibido y verificado?')
                ->action(fn () => (new TransactionService())->confirm($this->record)),
        ];
    }
}
