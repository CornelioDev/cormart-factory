<?php

namespace App\Services;

use App\Models\Parameter;
use App\Models\ParameterHistory;

class ParameterService
{
    public function get(string $key): float
    {
        $parameter = Parameter::where('key', $key)->firstOrFail();

        return (float) $parameter->value;
    }

    public function getAll(): array
    {
        return Parameter::all()->pluck('value', 'key')->map(fn($v) => (float) $v)->toArray();
    }

    public function update(string $key, float $value, string $period, int $changedBy): void
    {
        $parameter = Parameter::where('key', $key)->firstOrFail();

        ParameterHistory::create([
            'parameter_id'   => $parameter->id,
            'key'            => $key,
            'previous_value' => $parameter->value,
            'new_value'      => $value,
            'period'         => $period,
            'changed_by'     => $changedBy,
        ]);

        $parameter->update(['value' => $value]);
    }
}