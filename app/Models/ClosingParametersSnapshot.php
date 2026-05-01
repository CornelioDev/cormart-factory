<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClosingParametersSnapshot extends Model
{
    protected $fillable = [
        'monthly_closing_id',
        'key',
        'value',
    ];

    protected $table = 'closing_parameters_snapshot';

    public function monthlyClosing(): BelongsTo
    {
        return $this->belongsTo(MonthlyClosing::class);
    }
}