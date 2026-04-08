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

        if (! $settings) {
            return;
        }

        // Configurar remitente (aplica a todos los transportes)
        if ($settings->from_address) {
            config([
                'mail.from.address' => $settings->from_address,
                'mail.from.name'    => $settings->from_name,
            ]);
        }

        if ($settings->transport === 'sendmail') {
            config([
                'mail.default'              => 'sendmail',
                'mail.mailers.sendmail.path' => '/usr/sbin/sendmail -bs',
            ]);

            return;
        }

        // Transporte SMTP
        if (! $settings->host) {
            return;
        }

        $scheme   = match ($settings->encryption) {
            'smtps' => 'smtps',
            'smtp'  => 'smtp',
            default => 'smtp',
        };
        $isLocal  = in_array($settings->host, ['localhost', '127.0.0.1']);
        $user     = $settings->username ? urlencode($settings->username) : '';
        $pass     = $settings->decrypted_password ? urlencode($settings->decrypted_password) : '';
        $auth     = $user ? "{$user}:{$pass}@" : '';
        $query    = $isLocal ? '?verify_peer=0' : '';
        $url      = "{$scheme}://{$auth}{$settings->host}:{$settings->port}{$query}";

        config([
            'mail.default'           => 'smtp',
            'mail.mailers.smtp.url'  => $url,
            'mail.mailers.smtp.host' => null,
        ]);
    }
}
