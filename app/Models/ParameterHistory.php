<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParameterHistory extends Model
{
    protected $fillable = [
        'parameter_id',
        'key',
        'previous_value',
        'new_value',
        'period',
        'changed_by',
    ];

    protected $casts = [
        'previous_value' => 'decimal:4',
        'new_value'      => 'decimal:4',
    ];

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(Parameter::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}