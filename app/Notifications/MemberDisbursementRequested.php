<?php

namespace App\Notifications;

use App\Filament\Resources\FundMemberResource;
use App\Filament\Resources\TransactionResource;
use App\Models\FundMember;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberDisbursementRequested extends Notification
{
    use Queueable;

    public function __construct(
        protected Transaction $transaction,
        protected FundMember $member,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t = $this->transaction;

        return (new MailMessage)
            ->subject("Desembolso de ganancias registrado — {$this->member->name}")
            ->greeting("Hola {$notifiable->name},")
            ->line("Se ha registrado un desembolso de ganancias para un miembro del fondo.")
            ->line("**Miembro:** {$this->member->name}")
            ->line("**Monto:** RD$ " . number_format($t->amount, 2, '.', ','))
            ->line("**Banco:** {$t->bank}")
            ->line("**N° Transacción:** {$t->transaction_number}")
            ->line("**Fecha:** {$t->transaction_date->format('d/m/Y')}")
            ->action('Ver Transacción', url(TransactionResource::getUrl('view', ['record' => $t])))
            ->salutation('Cormart Factory');
    }
}
