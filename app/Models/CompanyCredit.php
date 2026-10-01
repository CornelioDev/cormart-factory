<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento del libro de saldos de una compañía frente al fondo.
 *
 * El saldo de la compañía es la suma con signo de sus movimientos:
 *   accrual     (+) cobro del deudor en cheque — el dinero quedó en manos de la compañía
 *   application (−) saldo aplicado a un desembolso
 *   settlement  (−) la compañía transfirió el efectivo al fondo
 *
 * Cada fila desglosa el monto en capital y mora, porque es ese desglose el que
 * decide a qué ledger se acredita cuando la compañía liquida.
 * Invariante por fila: amount = capital_amount + late_fee_amount.
 */
class CompanyCredit extends Model
{
    protected $fillable = [
        'company_id',
        'transaction_id',
        'type',
        'amount',
        'capital_amount',
        'late_fee_amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'capital_amount'  => 'decimal:2',
        'late_fee_amount' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
