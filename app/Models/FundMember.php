<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FundMember extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'type',
        'contribution',
        'fund_percentage',
        'active',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'contribution'    => 'decimal:2',
        'fund_percentage' => 'decimal:4',
        'active'          => 'boolean',
        'joined_at'       => 'date',
        'left_at'         => 'date',
    ];

    public function closingDistributions(): HasMany
    {
        return $this->hasMany(ClosingDistribution::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function earningDistributions(): HasMany
    {
        return $this->hasMany(Transaction::class)->where('type', 'earning_distribution');
    }

    public function earningsDisbursements(): HasMany
    {
        return $this->hasMany(Transaction::class)->where('type', 'member_disbursement');
    }

    public function totalEarned(): float
    {
        return round((float) $this->earningDistributions()
            ->where('status', 'confirmed')
            ->sum('amount'), 2);
    }

    public function totalDisbursed(): float
    {
        return round((float) $this->earningsDisbursements()
            ->where('status', 'confirmed')
            ->sum('amount'), 2);
    }

    public function earningsBalance(): float
    {
        return round($this->totalEarned() - $this->totalDisbursed(), 2);
    }
}