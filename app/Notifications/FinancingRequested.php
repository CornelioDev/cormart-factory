<?php

namespace App\Notifications;

use App\Filament\Resources\FinancingResource;
use App\Models\Financing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FinancingRequested extends Notification
{
    use Queueable;

    public function __construct(
        protected Financing $financing,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $f = $this->financing->load(['company', 'client']);

        return (new MailMessage)
            ->subject("Nueva solicitud de financiamiento — {$f->code}")
            ->greeting("Hola {$notifiable->name},")
            ->line("Se ha registrado una nueva solicitud de financiamiento.")
            ->line("**Código:** {$f->code}")
            ->line("**Compañía:** {$f->company->name}")
            ->line("**Deudor:** {$f->client->name}")
            ->line("**Monto:** RD$ " . number_format($f->amount, 2, '.', ','))
            ->line("**Plazo:** {$f->term_days} días")
            ->line("**Fecha de solicitud:** {$f->request_date->format('d/m/Y')}")
            ->action('Ver Financiamiento', url(FinancingResource::getUrl('view', ['record' => $f])))
            ->salutation('Cormart Factory');
    }
}
