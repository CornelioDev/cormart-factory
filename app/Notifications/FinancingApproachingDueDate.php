<?php

namespace App\Notifications;

use App\Filament\Resources\FinancingResource;
use App\Models\Financing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class FinancingApproachingDueDate extends Notification
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
            ->subject("Alerta: {$count} financiamiento(s) próximo(s) a vencer")
            ->greeting("Hola {$notifiable->name},")
            ->line("Los siguientes financiamientos están próximos a vencer:")
            ->line('');

        foreach ($this->financings as $f) {
            $amount   = 'RD$ ' . number_format($f->amount, 2, '.', ',');
            $dueDate  = $f->due_date->format('d/m/Y');
            $daysLeft = (int) now()->startOfDay()->diffInDays($f->due_date->startOfDay(), false);

            $message->line("**{$f->code}** — {$f->company->name} | Monto: {$amount} | Vence: {$dueDate} ({$daysLeft} día(s))");
        }

        // Link al primer financiamiento como referencia
        $first = $this->financings->first();

        $message
            ->line('')
            ->action('Ver Financiamientos', url(FinancingResource::getUrl('index')))
            ->salutation('Cormart Factory');

        return $message;
    }
}
