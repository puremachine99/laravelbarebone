<?php

namespace App\Notifications;

use App\Models\Card;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ManagerVerifiedNotification extends Notification
{
    use Queueable;

    public function __construct(public Card $card) {}

    public static function sendToOwner(Card $card): void
    {
        $owner = $card->employee?->user;

        if ($owner) {
            $owner->notify(new self($card));
        }
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'manager_verified',
            'icon' => 'o-check-badge',
            'title' => 'Prospek diverifikasi Manager',
            'message' => "Prospek \"{$this->card->title}\" kamu telah diverifikasi oleh Manager.",
            'url' => route('partnership.realisasi', ['card' => $this->card->id]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
