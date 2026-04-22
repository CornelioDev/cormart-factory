<?php

namespace App\Services;

use App\Models\CapitalAccount;
use Illuminate\Support\Facades\DB;

class CapitalAccountService
{
    public function credit(float $amount): void
    {
        CapitalAccount::instance()->credit($amount);
        DB::afterCommit(fn () => rescue(fn () => (new LedgerVerificationService())->verifyAndNotify()));
    }

    public function debit(float $amount): void
    {
        CapitalAccount::instance()->debit($amount);
        DB::afterCommit(fn () => rescue(fn () => (new LedgerVerificationService())->verifyAndNotify()));
    }

    public function getBalance(): float
    {
        return (float) CapitalAccount::instance()->balance;
    }
}
