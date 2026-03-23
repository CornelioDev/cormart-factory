<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\DistributionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class MonthlyClosingSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();

        // Autenticar al admin para que auth()->id() funcione en los services
        Auth::login($admin);

        $service = new DistributionService();

        foreach (['2026-01', '2026-02'] as $period) {
            $service->executeClosing($period, $admin->id);
        }

        Auth::logout();
    }
}
