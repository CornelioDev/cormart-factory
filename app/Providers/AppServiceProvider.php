<?php

namespace App\Providers;

use App\Models\Parameter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
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
