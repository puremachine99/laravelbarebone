<?php

namespace App\Notifications;

use App\Models\Card;
use App\Support\NotificationRecipients;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class DealClosedNotification extends Notification
{
    use Queueable;

    public function __construct(public Card $card) {}

    public static function sendToManagers(Card $card): void
    {
        NotificationFacade::send(NotificationRecipients::partnershipManagers(), new self($card));
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
        $pmoName = $this->card->employee?->user?->name ?? $this->card->employee?->name ?? 'Seorang PMO';

        return [
            'type' => 'deal_closed',
            'icon' => 'o-trophy',
            'title' => 'Deal berhasil closing',
            'message' => "{$pmoName} berhasil closing prospek \"{$this->card->title}\".",
            'url' => route('partnership.realisasi', ['card' => $this->card->id]),
        ];
    }

    /**
     * Dikirim lewat koneksi "sync" supaya siaran realtime tidak menunggu queue
     * worker -- BroadcastNotificationCreated bawaan Laravel implements ShouldBroadcast
     * (masuk antrian), bukan ShouldBroadcastNow.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toDatabase($notifiable)))
            ->onConnection('sync');
    }
}
