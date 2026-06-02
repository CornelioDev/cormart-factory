<?php

namespace Database\Seeders;

use App\Models\NotificationSetting;
use App\Support\NotificationType;
use Illuminate\Database\Seeder;

class NotificationSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (NotificationType::keys() as $key) {
            NotificationSetting::firstOrCreate(
                ['key' => $key],
                ['enabled' => true],
            );
        }
    }
}
