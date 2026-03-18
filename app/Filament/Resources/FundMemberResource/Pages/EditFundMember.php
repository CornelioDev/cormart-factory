<?php

namespace App\Filament\Resources\FundMemberResource\Pages;

use App\Filament\Resources\FundMemberResource;
use App\Services\FundMemberService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFundMember extends EditRecord
{
    protected static string $resource = FundMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        (new FundMemberService())->recalculateAllPercentages();
    }
}
