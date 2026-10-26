<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Convite para o usuário novo criar a senha (R-ACE-13).
 */
class UserInvitation extends Notification
{
    public function __construct(public string $url, public int $days) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Convite para o Yantra Painel'))
            ->greeting(__('Olá, :name.', ['name' => $notifiable->name]))
            ->line(__('A Weega criou um usuário para você no Yantra Painel.'))
            ->line(__('Clique no botão para criar a sua senha. No primeiro acesso, você cadastra o 2FA no aplicativo autenticador do celular.'))
            ->action(__('Criar a senha'), $this->url)
            ->line(__('O convite vale por :days dias. Depois disso, peça um novo convite ao administrador.', ['days' => $this->days]));
    }
}
