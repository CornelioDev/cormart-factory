<?php

namespace App\Services;

use App\Models\Financing;
use App\Models\Parameter;
use Carbon\Carbon;

class FinancingService
{
    public function calculateCommission(float $amount, int $termDays): float
    {
        $rate = (float) Parameter::where('key', 'commission_pct')->value('value');
        $multiplier = (int) ceil($termDays / 30);

        return round($amount * ($rate / 100) * $multiplier, 2);
    }

    public function calculateTransferAmount(float $amount, float $commission): float
    {
        return round($amount - $commission, 2);
    }

    public function calculateDueDate(Carbon $date, int $days): Carbon
    {
        return $date->copy()->addDays($days);
    }

    public function calculateLateFee(Financing $financing, Carbon $paymentDate): float
    {
        if (! $financing->due_date || $paymentDate->lte($financing->due_date)) {
            return 0.00;
        }

        $daysOverdue = $financing->due_date->diffInDays($paymentDate);
        $tiers = (int) ceil($daysOverdue / 30);
        $rate = (float) Parameter::where('key', 'late_fee_pct')->value('value');
        $balance = $financing->remainingBalance();

        return round($balance * ($rate / 100) * $tiers, 2);
    }
}
