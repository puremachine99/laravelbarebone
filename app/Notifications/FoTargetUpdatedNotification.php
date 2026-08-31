<?php

namespace App\Notifications;

use App\Models\Employee;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class FoTargetUpdatedNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, array{label: string, type: string, from: ?float, to: ?float}>  $changes
     */
    public function __construct(public Employee $employee, public array $changes, public int $year) {}

    /**
     * @param  array<int, array{label: string, type: string, from: ?float, to: ?float}>  $changes
     */
    public static function sendToOwner(Employee $employee, array $changes, int $year): void
    {
        if ($changes === []) {
            return;
        }

        $employee->user?->notify(new self($employee, $changes, $year));
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
        $details = collect($this->changes)
            ->map(fn (array $change) => "{$change['label']} ".self::format($change['from'], $change['type']).' → '.self::format($change['to'], $change['type']))
            ->implode(', ');

        return [
            'type' => 'fo_target_updated',
            'icon' => 'o-flag',
            'title' => 'Target kamu diperbarui Admin',
            'message' => "Target {$this->year} kamu diperbarui oleh Admin: {$details}.",
            'url' => route('investors.management-fo'),
        ];
    }

    private static function format(?float $value, string $type): string
    {
        if ($value === null) {
            return '-';
        }

        return $type === 'currency' ? Money::exact($value) : (string) (int) $value;
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
