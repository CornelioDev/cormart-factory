<?php

namespace App\Filament\Resources\MonthlyClosingResource\Pages;

use App\Filament\Resources\MonthlyClosingResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMonthlyClosing extends ViewRecord
{
    protected static string $resource = MonthlyClosingResource::class;

    public function getView(): string
    {
        return 'filament.resources.monthly-closing-resource.pages.view-monthly-closing';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}