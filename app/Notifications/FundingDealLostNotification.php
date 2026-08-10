<?php

namespace App\Notifications;

use App\Models\FoFunnel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FundingDealLostNotification extends Notification
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
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $contactName = $this->funnel->contact?->nama ?? 'prospek';

        return [
            'type' => 'funding_deal_lost',
            'icon' => 'o-x-circle',
            'title' => 'Deal investasi lost',
            'message' => "Prospek investasi dari {$contactName} dicatat sebagai lost.",
            'url' => route('investors.funnel'),
        ];
    }
}
