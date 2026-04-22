<?php

use App\Models\Parameter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\Commands\ScheduleCheckHeartbeatCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-financing-alerts')->dailyAt(
    rescue(fn () => Parameter::where('key', 'alert_send_time')->value('value'), '07:00', false) ?? '07:00'
);

Schedule::command('app:check-accounting-ledgers')->dailyAt(
    rescue(fn () => Parameter::where('key', 'alert_send_time')->value('value'), '07:00', false) ?? '07:00'
);

Schedule::command(ScheduleCheckHeartbeatCommand::class)->everyFiveMinutes();
Schedule::command(RunHealthChecksCommand::class)->everyFiveMinutes();
