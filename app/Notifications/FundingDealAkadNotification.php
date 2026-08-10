<?php

namespace App\Notifications;

use App\Models\FoFunnel;
use Illuminate\Bus\Queueable;
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
        return ['database'];
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
}
