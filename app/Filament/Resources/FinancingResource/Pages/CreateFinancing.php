<?php

namespace App\Filament\Resources\FinancingResource\Pages;

use App\Filament\Resources\FinancingResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFinancing extends CreateRecord
{
    protected static string $resource = FinancingResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['registered_by'] = auth()->id();

        return parent::handleRecordCreation($data);
    }
}
