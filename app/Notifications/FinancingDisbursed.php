<?php

namespace App\Notifications;

use App\Filament\Resources\FinancingResource;
use App\Filament\Resources\TransactionResource;
use App\Models\Financing;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FinancingDisbursed extends Notification
{
    use Queueable;

    public function __construct(
        protected Transaction $transaction,
        protected Collection $financings,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t     = $this->transaction;
        $this->financings->load('client');
        $codes = $this->financings->pluck('code')->implode(', ');

        $message = (new MailMessage)
            ->subject("Desembolso realizado — {$codes}")
            ->greeting("Hola {$notifiable->name},")
            ->line("Se ha realizado un desembolso para los siguientes financiamientos:")
            ->line('');

        foreach ($this->financings as $f) {
            $amount   = 'RD$ ' . number_format($f->amount, 2, '.', ',');
            $transfer = 'RD$ ' . number_format($f->transfer_amount, 2, '.', ',');
            $dueDate  = $f->due_date ? $f->due_date->format('d/m/Y') : '—';

            $message->line("**{$f->code}** — {$f->client->name} | Monto: {$amount} | Transferido: {$transfer} | Vence: {$dueDate}");
        }

        $totalTransfer = 'RD$ ' . number_format($t->amount, 2, '.', ',');

        $message
            ->line('')
            ->line("**Total desembolsado:** {$totalTransfer}")
            ->action('Ver Transacción', url(TransactionResource::getUrl('view', ['record' => $t])))
            ->salutation('Cormart Factory');

        return $message;
    }
}
