<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapitalAccount extends Model
{
    protected $table = 'capital_account';

    protected $fillable = ['balance'];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public static function instance(): static
    {
        return static::firstOrFail();
    }

    public function credit(float $amount): void
    {
        $this->increment('balance', round($amount, 2));
    }

    public function debit(float $amount): void
    {
        $this->decrement('balance', round($amount, 2));
    }
}
