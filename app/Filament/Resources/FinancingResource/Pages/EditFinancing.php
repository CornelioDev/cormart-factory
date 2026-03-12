<?php

namespace App\Filament\Resources\FinancingResource\Pages;

use App\Filament\Resources\FinancingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFinancing extends EditRecord
{
    protected static string $resource = FinancingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => $this->record->status === 'solicited'),
        ];
    }
}
