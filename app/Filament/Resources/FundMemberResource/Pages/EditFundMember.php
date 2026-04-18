<?php

namespace App\Filament\Resources\FundMemberResource\Pages;

use App\Filament\Resources\FundMemberResource;
use App\Services\CapitalAccountService;
use App\Services\FundMemberService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFundMember extends EditRecord
{
    protected static string $resource = FundMemberResource::class;

    protected ?float $originalContribution = null;

    protected ?bool $originalActive = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $this->originalContribution = (float) $this->record->contribution;
        $this->originalActive = (bool) $this->record->active;
    }

    protected function afterSave(): void
    {
        $member = $this->record->fresh();
        $capitalService = new CapitalAccountService();

        if ($this->originalActive && ! $member->active && $this->originalContribution > 0) {
            // Desactivado: devolver su capital del ledger
            $capitalService->debit($this->originalContribution);
        } elseif (! $this->originalActive && $member->active && (float) $member->contribution > 0) {
            // Reactivado: acreditar su capital al ledger
            $capitalService->credit((float) $member->contribution);
        } elseif ($member->active) {
            // Activo: ajustar por cambio en contribución
            $diff = (float) $member->contribution - $this->originalContribution;
            if ($diff > 0) {
                $capitalService->credit($diff);
            } elseif ($diff < 0) {
                $capitalService->debit(abs($diff));
            }
        }

        (new FundMemberService())->recalculateAllPercentages();
    }
}
