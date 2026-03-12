<?php

namespace App\Filament\Resources\FinancingResource\Pages;

use App\Filament\Resources\FinancingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFinancings extends ListRecords
{
    protected static string $resource = FinancingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
