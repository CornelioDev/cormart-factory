<?php

namespace App\Filament\Resources\MonthlyClosingResource\Pages;

use App\Filament\Resources\MonthlyClosingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMonthlyClosing extends EditRecord
{
    protected static string $resource = MonthlyClosingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
