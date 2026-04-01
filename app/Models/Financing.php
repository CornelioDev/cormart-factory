<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Financing extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'client_id',
        'code',
        'amount',
        'commission',
        'transfer_amount',
        'collected_amount',
        'late_fee_amount',
        'late_fee_pending',
        'term_days',
        'request_date',
        'due_date',
        'status',
        'cancellation_reason',
        'issue_period',
        'collection_period',
        'disbursed_at',
        'collected_at',
        'registered_by',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'commission'       => 'decimal:2',
        'transfer_amount'  => 'decimal:2',
        'collected_amount'  => 'decimal:2',
        'late_fee_amount'   => 'decimal:2',
        'late_fee_pending'  => 'decimal:2',
        'request_date'     => 'date',
        'due_date'         => 'date',
        'disbursed_at'     => 'date',
        'collected_at'     => 'date',
    ];

    protected static function booted(): void
    {
        static::created(function (Financing $financing) {
            $financing->updateQuietly([
                'code' => 'FN' . str_pad($financing->id, 6, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FinancingDocument::class);
    }

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(Transaction::class, 'transaction_financings')
                    ->withTimestamps();
    }

    public function remainingBalance(): float
    {
        return round((float) $this->amount - (float) $this->collected_amount, 2);
    }

    public function totalOwed(\Carbon\Carbon $asOfDate = null): float
    {
        $balance = $this->remainingBalance();
        $lateFee = app(\App\Services\FinancingService::class)->calculateLateFee($this, $asOfDate ?? now());

        return round($balance + $lateFee, 2);
    }
}
