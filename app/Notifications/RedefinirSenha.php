<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RedefinirSenha extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Torque: criar uma senha nova')
            ->greeting('Olá, '.explode(' ', trim($notifiable->name))[0].'!')
            ->line('Recebemos um pedido para criar uma senha nova para o seu acesso ao Torque.')
            ->action('Criar senha nova', route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]))
            ->line('O link vale por 60 minutos. Se não foi você, ignore este e-mail: sua senha continua a mesma.')
            ->salutation('Equipe Torque');
    }
}
