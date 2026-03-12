<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Transaction extends Model
{
    protected $fillable = [
        'type',
        'status',
        'amount',
        'bank',
        'transaction_number',
        'transaction_date',
        'company_id',
        'notes',
        'registered_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'transaction_date' => 'date',
        'confirmed_at'     => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function financings(): BelongsToMany
    {
        return $this->belongsToMany(Financing::class, 'transaction_financings')
                    ->withTimestamps();
    }
}
