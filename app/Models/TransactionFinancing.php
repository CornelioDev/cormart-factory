<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TransactionFinancing extends Pivot
{
    protected $table = 'transaction_financings';

    public $timestamps = true;
}
