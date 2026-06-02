<?php

namespace App\Services;

use App\Models\Financing;
use App\Models\Parameter;
use App\Models\User;
use Carbon\Carbon;
use RuntimeException;

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

    public function confirmReceipt(Financing $financing, User $by, ?Carbon $on = null): Financing
    {
        if ($financing->confirmed_at !== null) {
            throw new RuntimeException('Este financiamiento ya fue confirmado.');
        }

        if (! in_array($financing->status, ['disbursed', 'partially_collected'], true)) {
            throw new RuntimeException('Solo se puede confirmar un financiamiento totalmente desembolsado.');
        }

        $isOwnCompanyUser = $by->hasRole('company_user') && (int) $by->company_id === (int) $financing->company_id;
        if (! $by->hasRole('super_admin') && ! $isOwnCompanyUser) {
            throw new RuntimeException('No tiene permiso para confirmar la recepción de este financiamiento.');
        }

        $confirmedAt = $on ?? Carbon::today();

        $financing->update([
            'confirmed_at' => $confirmedAt,
            'confirmed_by' => $by->id,
            'due_date'     => $this->calculateDueDate($confirmedAt, (int) $financing->term_days),
        ]);

        return $financing->refresh();
    }

    public function calculateLateFee(Financing $financing, Carbon $paymentDate): float
    {
        if (! $financing->due_date || $paymentDate->lte($financing->due_date)) {
            return 0.00;
        }

        $daysOverdue = $financing->due_date->diffInDays($paymentDate);
        $tiers = (int) floor($daysOverdue / 30);
        $rate = (float) Parameter::where('key', 'late_fee_pct')->value('value');
        $balance = $financing->remainingBalance();

        return round($balance * ($rate / 100) * $tiers, 2);
    }
}
