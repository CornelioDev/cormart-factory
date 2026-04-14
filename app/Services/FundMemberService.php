<?php

namespace App\Services;

use App\Models\FundMember;

class FundMemberService
{
    public function calculatePercentage(float $contribution): float
    {
        $totalCapital = (float) FundMember::where('active', true)
            ->where(fn ($q) => $q->where('type', 'capital')
                ->orWhere(fn ($q2) => $q2->where('type', 'in_kind')->where('contribution', '>', 0))
            )
            ->sum('contribution');

        if ($totalCapital <= 0) {
            return 100.0;
        }

        return round(($contribution / $totalCapital) * 100, 4);
    }

    public function recalculateAllPercentages(): void
    {
        $eligible = FundMember::where('active', true)
            ->where(fn ($q) => $q->where('type', 'capital')
                ->orWhere(fn ($q2) => $q2->where('type', 'in_kind')->where('contribution', '>', 0))
            )
            ->get();

        $totalCapital = (float) $eligible->sum('contribution');

        if ($totalCapital <= 0) {
            return;
        }

        $eligible->each(function (FundMember $member) use ($totalCapital) {
            $member->updateQuietly([
                'fund_percentage' => round(($member->contribution / $totalCapital) * 100, 4),
            ]);
        });

        // Miembros inactivos o in_kind sin capital → 0%
        FundMember::where(fn ($q) => $q->where('active', false)
            ->orWhere(fn ($q2) => $q2->where('type', 'in_kind')->where('contribution', 0))
        )
            ->where('fund_percentage', '>', 0)
            ->each(function (FundMember $member) {
                $member->updateQuietly(['fund_percentage' => 0]);
            });
    }
}
