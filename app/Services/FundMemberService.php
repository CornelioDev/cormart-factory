<?php

namespace App\Services;

use App\Models\FundMember;

class FundMemberService
{
    public function calculatePercentage(float $contribution): float
    {
        $totalCapital = (float) FundMember::where('type', 'capital')
            ->where('active', true)
            ->sum('contribution');

        if ($totalCapital <= 0) {
            return 100.0;
        }

        return round(($contribution / $totalCapital) * 100, 4);
    }

    public function recalculateAllPercentages(): void
    {
        $totalCapital = (float) FundMember::where('type', 'capital')
            ->where('active', true)
            ->sum('contribution');

        if ($totalCapital <= 0) {
            return;
        }

        FundMember::where('type', 'capital')
            ->where('active', true)
            ->each(function (FundMember $member) use ($totalCapital) {
                $member->updateQuietly([
                    'fund_percentage' => round(($member->contribution / $totalCapital) * 100, 4),
                ]);
            });

        // Set inactive capital members to 0%
        FundMember::where('type', 'capital')
            ->where('active', false)
            ->where('fund_percentage', '>', 0)
            ->each(function (FundMember $member) {
                $member->updateQuietly(['fund_percentage' => 0]);
            });
    }
}
