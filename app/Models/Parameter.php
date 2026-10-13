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

    /**
     * Un porcentaje tal como se muestra en pantalla y en las notas: siempre con
     * dos decimales (0.20, no 0.2). Única forma de escribirlo, para que el mismo
     * impuesto no aparezca con dos grafías según dónde se mire.
     */
    public static function formatPct(float|string|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ParameterHistory::class);
    }
}