<?php

namespace App\Services;

use App\Models\FundMember;

class FundMemberService
{
    public function calculatePercentage(float $contribution): float
    {
        // fund_percentage solo aplica a miembros de capital. Los in_kind híbridos
        // reciben in_kind_payment + fixed_return, pero no proporcional capital.
        $totalCapital = (float) FundMember::where('active', true)
            ->where('type', 'capital')
            ->sum('contribution');

        if ($totalCapital <= 0) {
            return 100.0;
        }

        return round(($contribution / $totalCapital) * 100, 4);
    }

    public function recalculateAllPercentages(): void
    {
        $eligible = FundMember::where('active', true)
            ->where('type', 'capital')
            ->get();

        $totalCapital = (float) $eligible->sum('contribution');

        $eligible->each(function (FundMember $member) use ($totalCapital) {
            $member->updateQuietly([
                'fund_percentage' => $totalCapital > 0
                    ? round(($member->contribution / $totalCapital) * 100, 4)
                    : 0,
            ]);
        });

        // Miembros inactivos o in_kind nunca tienen fund_percentage.
        FundMember::where(fn ($q) => $q->where('active', false)->orWhere('type', 'in_kind'))
            ->where('fund_percentage', '>', 0)
            ->each(function (FundMember $member) {
                $member->updateQuietly(['fund_percentage' => 0]);
            });
    }
}
