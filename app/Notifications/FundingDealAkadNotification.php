<?php

namespace App\Notifications;

use App\Models\FoFunnel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class FundingDealAkadNotification extends Notification
{
    use Queueable;

    public function __construct(public FoFunnel $funnel) {}

    public static function sendToOwner(FoFunnel $funnel): void
    {
        $funnel->employee?->user?->notify(new self($funnel));
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
        $contactName = $this->funnel->contact?->nama ?? 'prospek';

        return [
            'type' => 'funding_deal_akad',
            'icon' => 'o-document-check',
            'title' => 'Akad investasi tercatat',
            'message' => "Proses akad investasi dari {$contactName} sudah tercatat.",
            'url' => route('investors.funnel'),
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
