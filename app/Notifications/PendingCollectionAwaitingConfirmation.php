<?php

namespace App\Notifications;

use App\Filament\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendingCollectionAwaitingConfirmation extends Notification
{
    use Queueable;

    public function __construct(
        protected Transaction $transaction,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t = $this->transaction->load(['company', 'registeredBy']);

        return (new MailMessage)
            ->subject("Cobro pendiente de confirmación — {$t->transaction_number}")
            ->greeting("Hola {$notifiable->name},")
            ->line("Se ha registrado un cobro que requiere confirmación.")
            ->line("**N° Transacción:** {$t->transaction_number}")
            ->line("**Compañía:** {$t->company?->name}")
            ->line("**Monto:** RD$ " . number_format($t->amount, 2, '.', ','))
            ->line("**Registrado por:** {$t->registeredBy?->name}")
            ->line("**Fecha:** {$t->transaction_date->format('d/m/Y')}")
            ->action('Ver Transacción', url(TransactionResource::getUrl('view', ['record' => $t])))
            ->salutation('Cormart Factory');
    }
}
