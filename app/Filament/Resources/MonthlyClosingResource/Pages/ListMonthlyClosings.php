<?php

namespace App\Filament\Resources\MonthlyClosingResource\Pages;

use App\Filament\Resources\MonthlyClosingResource;
use Filament\Resources\Pages\ListRecords;

class ListMonthlyClosings extends ListRecords
{
    protected static string $resource = MonthlyClosingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}