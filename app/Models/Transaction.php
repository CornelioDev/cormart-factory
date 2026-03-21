<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Transaction extends Model
{
    protected $fillable = [
        'code',
        'type',
        'status',
        'amount',
        'bank',
        'transaction_number',
        'transaction_date',
        'company_id',
        'supplier_id',
        'fund_member_id',
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

    protected static function booted(): void
    {
        static::created(function (Transaction $transaction) {
            $transaction->updateQuietly([
                'code' => 'TX' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function fundMember(): BelongsTo
    {
        return $this->belongsTo(FundMember::class);
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

    public function getBeneficiario(): ?string
    {
        return match ($this->type) {
            'expense'                => $this->supplier?->name,
            'earning_distribution',
            'member_disbursement'    => $this->fundMember?->name,
            default                  => $this->company?->name,
        };
    }
}
