<?php

namespace App\Services;

use App\Models\Parameter;
use Carbon\Carbon;

class FinancingService
{
    public function calculateCommission(float $amount): float
    {
        $rate = (float) Parameter::where('key', 'commission_pct')->value('value');

        return round($amount * ($rate / 100), 2);
    }

    public function calculateTransferAmount(float $amount, float $commission): float
    {
        return round($amount - $commission, 2);
    }

    public function calculateDueDate(Carbon $date, int $days): Carbon
    {
        return $date->copy()->addDays($days);
    }
}
