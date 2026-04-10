<?php

namespace App\Health\Checks;

use App\Models\MailSetting;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class MailConfigurationCheck extends Check
{
    public function run(): Result
    {
        $settings = MailSetting::first();

        if (! $settings) {
            return Result::make()->failed('No hay configuración de correo.');
        }

        if (! $settings->from_address) {
            return Result::make()->warning('Falta dirección de remitente.');
        }

        return Result::make()->ok();
    }
}
