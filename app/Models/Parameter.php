<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Parameter extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    public function history(): HasMany
    {
        return $this->hasMany(ParameterHistory::class);
    }
}