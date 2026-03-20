<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Services\TransactionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $financingIds = $data['financing_ids'] ?? [];
        unset($data['financing_ids']);

        $data['registered_by'] = auth()->id();

        if ($data['type'] === 'expense') {
            return (new TransactionService())->createExpense($data);
        }

        return (new TransactionService())->create($data, $financingIds);
    }
}
