<?php

namespace App\Notifications;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FoTargetUpdatedNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $changedMetricLabels
     */
    public function __construct(public Employee $employee, public array $changedMetricLabels, public int $year) {}

    /**
     * @param  array<int, string>  $changedMetricLabels
     */
    public static function sendToOwner(Employee $employee, array $changedMetricLabels, int $year): void
    {
        if ($changedMetricLabels === []) {
            return;
        }

        $employee->user?->notify(new self($employee, $changedMetricLabels, $year));
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
        $metrics = implode(', ', $this->changedMetricLabels);

        return [
            'type' => 'fo_target_updated',
            'icon' => 'o-flag',
            'title' => 'Target kamu diperbarui Admin',
            'message' => "Target {$this->year} kamu untuk {$metrics} telah diperbarui oleh Admin.",
            'url' => route('investors.management-fo'),
        ];
    }
}
