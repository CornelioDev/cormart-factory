<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClosingDistribution extends Model
{
    protected $fillable = [
        'monthly_closing_id',
        'fund_member_id',
        'fixed_amount',
        'proportional_amount',
        'total_amount',
    ];

    protected $casts = [
        'fixed_amount'        => 'decimal:2',
        'proportional_amount' => 'decimal:2',
        'total_amount'        => 'decimal:2',
    ];

    public function monthlyClosing(): BelongsTo
    {
        return $this->belongsTo(MonthlyClosing::class);
    }

    public function fundMember(): BelongsTo
    {
        return $this->belongsTo(FundMember::class);
    }
}