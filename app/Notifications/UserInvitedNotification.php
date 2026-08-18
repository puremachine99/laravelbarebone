<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitedNotification extends Notification
{
    use Queueable;

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
        $url = route('password.setup', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Anda diundang ke '.config('app.name'))
            ->greeting('Selamat, '.$notifiable->name.'!')
            ->line('Admin telah membuat akun untuk Anda di '.config('app.name').'.')
            ->line('Klik tombol di bawah untuk membuat password dan mengaktifkan akun Anda.')
            ->action('Buat Password', $url)
            ->line('Link ini berlaku selama 72 jam. Jika Anda tidak menduga akan menerima email ini, abaikan saja.');
    }
}
