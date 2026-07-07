<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi · Kheedma Academy')
            ->greeting('Assalamu\'alaikum, '.$notifiable->name.'.')
            ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akunmu.')
            ->action('Atur Ulang Kata Sandi', $url)
            ->line('Tautan ini berlaku 60 menit. Kalau kamu tidak meminta pengaturan ulang, abaikan email ini.')
            ->salutation('Salam hangat, Tim Kheedma Academy');
    }
}
