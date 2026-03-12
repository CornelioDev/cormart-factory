<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundMember extends Model
{
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
}