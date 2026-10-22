<?php

namespace App\Enums;

/**
 * Jenis instansi in pmo_opds. Every jenis except Kementrian belongs to a kabupaten/kota (regency_id); Kementrian is
 * national and belongs to a region (region_id) instead.
 */
enum OpdJenis: string
{
    case Opd = 'OPD';
    case Kementerian = 'Kementrian';
    case UnitKesehatan = 'Unit Kesehatan';
    case Pendidikan = 'Pendidikan';

    /**
     * Region every Kementerian belongs to when none is given.
     */
    public const KEMENTERIAN_DEFAULT_REGION_CODE = 'REG-3';

    public function label(): string
    {
        return match ($this) {
            self::Opd => __('OPD'),
            self::Kementerian => __('Kementerian / Lembaga'),
            self::UnitKesehatan => __('Unit Kesehatan'),
            self::Pendidikan => __('Pendidikan'),
        };
    }

    /**
     * Whether this jenis sits in a kabupaten/kota (regency_id) rather than directly in a region.
     */
    public function hasLocation(): bool
    {
        return $this !== self::Kementerian;
    }

    /**
     * Case-insensitive lookup that also accepts the "Kementerian" spelling.
     */
    public static function fromName(?string $jenis): ?self
    {
        $normalized = mb_strtolower(trim((string) $jenis));

        if ($normalized === 'kementerian') {
            return self::Kementerian;
        }

        foreach (self::cases() as $case) {
            if (mb_strtolower($case->value) === $normalized) {
                return $case;
            }
        }

        return null;
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
