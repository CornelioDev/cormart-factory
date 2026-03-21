<?php

namespace App\Services;

use App\Models\FundAccount;

class FundAccountService
{
    public function credit(float $amount): void
    {
        FundAccount::instance()->credit($amount);
    }

    public function debit(float $amount): void
    {
        FundAccount::instance()->debit($amount);
    }

    public function getBalance(): float
    {
        return (float) FundAccount::instance()->balance;
    }
}
