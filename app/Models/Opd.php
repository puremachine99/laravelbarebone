<?php

namespace App\Models;

use App\Enums\OpdJenis;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An instansi of any jenis (see OpdJenis). OPD, Unit Kesehatan and Pendidikan belong to a kabupaten/kota and get
 * their province and region through it; Kementerian has no kabupaten/kota and belongs to a region directly.
 */
class Opd extends Model
{
    use HasFactory;

    protected $table = 'pmo_opds';

    protected $guarded = ['id'];

    /**
     * Jenis that are national bodies: they are not tied to a provinsi or kabupaten/kota, so location filters skip
     * them and the API returns no regency or provinsi for them.
     *
     * @var list<string>
     */
    public const JENIS_WITHOUT_LOCATION = ['kementrian', 'kementerian'];

    protected static function booted(): void
    {
        static::saving(function (Opd $opd) {
            $jenis = OpdJenis::fromName($opd->jenis);

            if ($jenis) {
                $opd->jenis = $jenis->value;
            }

            if ($jenis === OpdJenis::Kementerian) {
                $opd->regency_id = null;
                $opd->region_id ??= Region::query()->where('code', OpdJenis::KEMENTERIAN_DEFAULT_REGION_CODE)->value('id');
            } else {
                $opd->region_id = null;
            }
        });
    }

    public static function jenisHasLocation(?string $jenis): bool
    {
        return ! in_array(mb_strtolower(trim((string) $jenis)), self::JENIS_WITHOUT_LOCATION, true);
    }

    public function hasLocation(): bool
    {
        return self::jenisHasLocation($this->jenis);
    }

    public function jenisEnum(): ?OpdJenis
    {
        return OpdJenis::fromName($this->jenis);
    }

    /**
     * @return BelongsTo<Regency, $this>
     */
    public function regency(): BelongsTo
    {
        return $this->belongsTo(Regency::class);
    }

    /**
     * Only set for Kementerian; other jenis reach their region through the regency (see effectiveRegion()).
     *
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * The region of this instansi whatever its jenis. Load "region" and "regency.region" first to avoid queries.
     */
    public function effectiveRegion(): ?Region
    {
        return $this->region_id ? $this->region : $this->regency?->region;
    }

    /**
     * @return BelongsToMany<Urusan, $this>
     */
    public function urusans(): BelongsToMany
    {
        return $this->belongsToMany(Urusan::class, 'pmo_opd_urusan', 'opd_id', 'urusan_id');
    }

    /**
     * Exact jenis match, case-insensitive.
     *
     * @param  Builder<Opd>  $query
     */
    public function scopeOfJenis(Builder $query, OpdJenis|string|null $jenis): void
    {
        if ($jenis instanceof OpdJenis) {
            $jenis = $jenis->value;
        }

        if (filled($jenis)) {
            $query->whereRaw('LOWER(pmo_opds.jenis) = ?', [mb_strtolower(trim($jenis))]);
        }
    }

    /**
     * @param  Builder<Opd>  $query
     */
    public function scopeKementerian(Builder $query): void
    {
        $query->where('pmo_opds.jenis', OpdJenis::Kementerian->value);
    }

    /**
     * @param  Builder<Opd>  $query
     */
    public function scopeUnitKesehatan(Builder $query): void
    {
        $query->where('pmo_opds.jenis', OpdJenis::UnitKesehatan->value);
    }

    /**
     * @param  Builder<Opd>  $query
     */
    public function scopePendidikan(Builder $query): void
    {
        $query->where('pmo_opds.jenis', OpdJenis::Pendidikan->value);
    }

    /**
     * Only OPDs that belong to a provinsi / kabupaten/kota (everything except the national jenis like Kementrian).
     *
     * @param  Builder<Opd>  $query
     */
    public function scopeWithLocation(Builder $query): void
    {
        $query->where(fn (Builder $located) => $located
            ->whereNull('pmo_opds.jenis')
            ->orWhereRaw('LOWER(pmo_opds.jenis) NOT IN ('.implode(',', array_fill(0, count(self::JENIS_WITHOUT_LOCATION), '?')).')', self::JENIS_WITHOUT_LOCATION));
    }

    /**
     * Instansi of a region: Kementerian by their own region, the rest by their regency's region.
     *
     * @param  Builder<Opd>  $query
     */
    public function scopeInRegion(Builder $query, int|string|null $regionId): void
    {
        if (blank($regionId)) {
            return;
        }

        $query->where(fn (Builder $inRegion) => $inRegion
            ->where('pmo_opds.region_id', $regionId)
            ->orWhereHas('regency', fn (Builder $regency) => $regency->where('regencies.region_id', $regionId)));
    }

    /**
     * Free-text search across every column shown for an OPD: name, jenis, regency, province,
     * region and urusan (name and code). Whitespace-separated keywords must all match,
     * each in any of those columns.
     *
     * @param  Builder<Opd>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        foreach (preg_split('/\s+/', mb_strtolower(trim((string) $term)), -1, PREG_SPLIT_NO_EMPTY) as $keyword) {
            $like = '%'.addcslashes($keyword, '%_\\').'%';

            $query->where(fn (Builder $match) => $match
                ->whereRaw('LOWER(pmo_opds.name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(pmo_opds.jenis) LIKE ?', [$like])
                ->orWhereHas('region', fn ($region) => $region->whereRaw('LOWER(regions.name) LIKE ?', [$like]))
                ->orWhereHas('regency', fn ($regency) => $regency
                    ->whereRaw('LOWER(regencies.name) LIKE ?', [$like])
                    ->orWhereHas('province', fn ($province) => $province->whereRaw('LOWER(provinces.name) LIKE ?', [$like]))
                    ->orWhereHas('region', fn ($region) => $region->whereRaw('LOWER(regions.name) LIKE ?', [$like])))
                ->orWhereHas('urusans', fn ($urusan) => $urusan
                    ->whereRaw('LOWER(pmo_urusans.urusan) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(pmo_urusans.code) LIKE ?', [$like])));
        }
    }
}
