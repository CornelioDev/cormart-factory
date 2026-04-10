<?php

use App\Models\Parameter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-financing-alerts')->dailyAt(
    rescue(fn () => Parameter::where('key', 'alert_send_time')->value('value'), '07:00', false) ?? '07:00'
);
