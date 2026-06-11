<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordLink extends Notification
{
    use Queueable;

    public function __construct(public string $url)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe — ALTEN')
            ->greeting('Bonjour ' . ($notifiable->full_name ?? '') . ',')
            ->line('Vous avez demandé la réinitialisation de votre mot de passe sur l’espace interne ALTEN.')
            ->action('Réinitialiser mon mot de passe', $this->url)
            ->line('Ce lien est valable 60 minutes.')
            ->line("Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet e-mail.")
            ->salutation('— Espace interne ALTEN');
    }
}
