<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancingDocument extends Model
{
    protected $fillable = [
        'financing_id',
        'type',
        'document_number',
        'document_date',
        'file_path',
    ];

    protected $casts = [
        'document_date' => 'date',
    ];

    public function financing(): BelongsTo
    {
        return $this->belongsTo(Financing::class);
    }
}
