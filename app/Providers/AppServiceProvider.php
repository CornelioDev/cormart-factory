<?php

namespace App\Providers;

use App\Health\Checks\MailConfigurationCheck;
use App\Models\Parameter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('view-logs', fn ($user) => $user->hasRole('super_admin'));

        Health::checks([
            DatabaseCheck::new(),
            UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(80)
                ->failWhenUsedSpaceIsAbovePercentage(90),
            ScheduleCheck::new()->heartbeatMaxAgeInMinutes(10),
            DebugModeCheck::new(),
            EnvironmentCheck::new(),
            MailConfigurationCheck::new()->name('Correo'),
        ]);

        if (! Schema::hasTable('parameters')) {
            return;
        }

        $timezone = rescue(
            fn () => Parameter::where('key', 'timezone')->value('value'),
            null,
            false
        );

        if ($timezone && in_array($timezone, timezone_identifiers_list())) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }
    }
}
