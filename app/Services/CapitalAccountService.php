<?php

namespace App\Services;

use App\Models\CapitalAccount;

class CapitalAccountService
{
    public function credit(float $amount): void
    {
        CapitalAccount::instance()->credit($amount);
    }

    public function debit(float $amount): void
    {
        CapitalAccount::instance()->debit($amount);
    }

    public function getBalance(): float
    {
        return (float) CapitalAccount::instance()->balance;
    }
}
