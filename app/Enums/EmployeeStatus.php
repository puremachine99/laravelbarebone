<?php

namespace App\Enums;

enum EmployeeStatus: string
{
    case Active = 'active';
    case Resign = 'resign';
    case Discontinued = 'discontinued';
    case Phk = 'phk';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Aktif'),
            self::Resign => __('Resign'),
            self::Discontinued => __('Discontinued'),
            self::Phk => __('PHK'),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'badge-success',
            self::Resign => 'badge-warning',
            self::Discontinued => 'badge-ghost',
            self::Phk => 'badge-error',
        };
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['id' => $case->value, 'name' => $case->label()],
            self::cases()
        );
    }
}
