<?php

namespace App\Enums;

enum ChatPresenceStatus: string
{
    case Online = 'online';
    case Away = 'away';
    case Busy = 'busy';
    case Offline = 'offline';

    public function label(): string
    {
        return match ($this) {
            self::Online => __('Online'),
            self::Away => __('Pergi Sebentar'),
            self::Busy => __('Sibuk'),
            self::Offline => __('Offline'),
        };
    }

    public function dotColorClass(): string
    {
        return match ($this) {
            self::Online => 'bg-green-500',
            self::Away => 'bg-amber-500',
            self::Busy => 'bg-red-500',
            self::Offline => 'bg-base-content/30',
        };
    }
}
