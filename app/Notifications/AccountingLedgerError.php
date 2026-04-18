<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountingLedgerError extends Notification
{
    use Queueable;

    public function __construct(
        protected array $failedChecks,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Error contable detectado — Reconciliación')
            ->greeting("Hola {$notifiable->name},")
            ->line('La verificación automática de ledgers ha detectado las siguientes discrepancias:');

        foreach ($this->failedChecks as $check) {
            $message->line("---")
                ->line("**{$check['name']}** — Diferencia: RD$ " . number_format($check['diff'], 2, '.', ','))
                ->line("Esperado: RD$ " . number_format($check['expected'], 2, '.', ',')
                    . " | Actual: RD$ " . number_format($check['actual'], 2, '.', ','))
                ->line($check['detail']);
        }

        return $message
            ->action('Ver Reconciliación', url('/admin/reconciliacion'))
            ->salutation('Cormart Factory');
    }
}
