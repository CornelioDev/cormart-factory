<?php

namespace App\Services;

use App\Models\FundAccount;
use Illuminate\Support\Facades\DB;

class FundAccountService
{
    public function credit(float $amount): void
    {
        FundAccount::instance()->credit($amount);
        DB::afterCommit(fn () => rescue(fn () => (new LedgerVerificationService())->verifyAndNotify()));
    }

    public function debit(float $amount): void
    {
        FundAccount::instance()->debit($amount);
        DB::afterCommit(fn () => rescue(fn () => (new LedgerVerificationService())->verifyAndNotify()));
    }

    public function getBalance(): float
    {
        return (float) FundAccount::instance()->balance;
    }
}
