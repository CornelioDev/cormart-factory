<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TransactionResource::configureConfirmAction(Action::make('confirm')),
            TransactionResource::configureCorrectAction(Action::make('correct')),
            TransactionResource::configureRejectAction(Action::make('reject')),
        ];
    }
}
