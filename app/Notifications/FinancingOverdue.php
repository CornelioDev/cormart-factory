<?php

namespace App\Notifications;

use App\Filament\Resources\FinancingResource;
use App\Models\Financing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class FinancingOverdue extends Notification
{
    use Queueable;

    public function __construct(
        protected Collection $financings,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->financings->count();

        $message = (new MailMessage)
            ->subject("Alerta: {$count} financiamiento(s) vencido(s)")
            ->greeting("Hola {$notifiable->name},")
            ->line("Los siguientes financiamientos están vencidos:")
            ->line('');

        foreach ($this->financings as $f) {
            $amount      = 'RD$ ' . number_format($f->amount, 2, '.', ',');
            $remaining   = 'RD$ ' . number_format($f->remainingBalance(), 2, '.', ',');
            $dueDate     = $f->due_date->format('d/m/Y');
            $daysOverdue = (int) $f->due_date->startOfDay()->diffInDays(now()->startOfDay());

            $message->line("**{$f->code}** — {$f->client->name} | Monto: {$amount} | Pendiente: {$remaining} | Venció: {$dueDate} ({$daysOverdue} día(s) de atraso)");
        }

        $message
            ->line('')
            ->action('Ver Financiamientos', url(FinancingResource::getUrl('index')))
            ->salutation('Cormart Factory');

        return $message;
    }
}
