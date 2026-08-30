<?php

namespace App\Notifications;

use App\Models\FoFunnel;
use App\Support\NotificationRecipients;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class FundingDealClosedNotification extends Notification
{
    use Queueable;

    public function __construct(public FoFunnel $funnel) {}

    public static function sendToAdmins(FoFunnel $funnel): void
    {
        NotificationFacade::send(NotificationRecipients::investorAdmins(), new self($funnel));
    }

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
        $foName = $this->funnel->employee?->user?->name ?? $this->funnel->employee?->name ?? 'Seorang FO';
        $contactName = $this->funnel->contact?->nama ?? 'prospek';

        return [
            'type' => 'funding_deal_closed',
            'icon' => 'o-trophy',
            'title' => 'Deal investasi berhasil closing',
            'message' => "{$foName} berhasil closing investasi dari {$contactName}.",
            'url' => route('investors.funnel'),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
