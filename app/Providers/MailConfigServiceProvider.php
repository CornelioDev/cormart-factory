<?php

namespace App\Providers;

use App\Models\MailSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class MailConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Solo sobreescribir si la tabla existe y tiene un registro configurado
        if (! Schema::hasTable('mail_settings')) {
            return;
        }

        $settings = MailSetting::first();

        if (! $settings || ! $settings->host) {
            return;
        }

        config([
            'mail.default'                  => 'smtp',
            'mail.mailers.smtp.host'        => $settings->host,
            'mail.mailers.smtp.port'        => $settings->port,
            'mail.mailers.smtp.username'    => $settings->username,
            'mail.mailers.smtp.password'    => $settings->decrypted_password,
            'mail.mailers.smtp.scheme'      => $settings->encryption === 'none' ? null : $settings->encryption,
            'mail.from.address'             => $settings->from_address,
            'mail.from.name'                => $settings->from_name,
        ]);
    }
}
