<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyClosing extends Model
{
    protected $fillable = [
        'period',
        'total_commissions',
        'total_fixed',
        'net_profit',
        'reserve',
        'post_reserve',
        'in_kind_payment',
        'available_for_capital',
        'verification_diff',
        'executed_by',
        'closed_at',
    ];

    protected $casts = [
        'total_commissions'     => 'decimal:2',
        'total_fixed'           => 'decimal:2',
        'net_profit'            => 'decimal:2',
        'reserve'               => 'decimal:2',
        'post_reserve'          => 'decimal:2',
        'in_kind_payment'       => 'decimal:2',
        'available_for_capital' => 'decimal:2',
        'verification_diff'     => 'decimal:2',
        'closed_at'             => 'datetime',
    ];

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(ClosingDistribution::class);
    }

    public function parametersSnapshot(): HasMany
    {
        return $this->hasMany(ClosingParametersSnapshot::class);
    }
}