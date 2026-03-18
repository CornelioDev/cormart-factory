<?php

namespace App\Filament\Resources\FundMemberResource\Pages;

use App\Filament\Resources\FundMemberResource;
use App\Services\FundMemberService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateFundMember extends CreateRecord
{
    protected static string $resource = FundMemberResource::class;

    protected function afterCreate(): void
    {
        (new FundMemberService())->recalculateAllPercentages();
    }
}
