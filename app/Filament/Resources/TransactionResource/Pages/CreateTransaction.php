<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Services\TransactionService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $financingIds = $data['financing_ids'] ?? [];
        unset($data['financing_ids']);

        $data['registered_by'] = auth()->id();

        // company_user: campos ocultos en el form, asegurar valores desde query params
        $user = auth()->user();
        if ($user->hasRole('company_user')) {
            $data['type']       ??= request()->query('type', 'collection');
            $data['company_id'] ??= $user->company_id;
        }

        try {
            if (($data['type'] ?? null) === 'expense') {
                return (new TransactionService())->createExpense($data);
            }

            return (new TransactionService())->create($data, $financingIds);
        } catch (\Exception $e) {
            Notification::make()
                ->title('No se pudo registrar la transacción')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            throw new Halt();
        }
    }
}
