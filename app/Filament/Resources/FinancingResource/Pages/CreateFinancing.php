<?php

namespace App\Filament\Resources\FinancingResource\Pages;

use App\Filament\Resources\FinancingResource;
use App\Services\NotificationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFinancing extends CreateRecord
{
    protected static string $resource = FinancingResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['registered_by'] = auth()->id();

        $user = auth()->user();
        if ($user->hasRole('company_user')) {
            $data['company_id'] ??= $user->company_id;
        }

        $financing = parent::handleRecordCreation($data);

        rescue(fn () => app(NotificationService::class)->financingRequested($financing));

        return $financing;
    }
}
