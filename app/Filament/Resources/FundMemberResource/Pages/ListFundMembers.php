<?php

namespace App\Filament\Resources\FundMemberResource\Pages;

use App\Filament\Resources\FundMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFundMembers extends ListRecords
{
    protected static string $resource = FundMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
