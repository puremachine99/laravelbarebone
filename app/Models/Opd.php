<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Opd extends Model
{
    use HasFactory;

    protected $table = 'pmo_opds';

    protected $guarded = ['id'];

    /**
     * Jenis that are national bodies: they are not tied to a provinsi or kabupaten/kota (their regency_id is only a
     * placeholder), so location filters skip them and the API returns no location for them.
     *
     * @var list<string>
     */
    public const JENIS_WITHOUT_LOCATION = ['kementrian', 'kementerian'];

    public static function jenisHasLocation(?string $jenis): bool
    {
        return ! in_array(mb_strtolower(trim((string) $jenis)), self::JENIS_WITHOUT_LOCATION, true);
    }

    public function hasLocation(): bool
    {
        return self::jenisHasLocation($this->jenis);
    }

    public function regency()
    {
        return $this->belongsTo(Regency::class);
    }

    public function urusans()
    {
        return $this->belongsToMany(Urusan::class, 'pmo_opd_urusan', 'opd_id', 'urusan_id');
    }

    /**
     * Exact jenis match, case-insensitive.
     *
     * @param  Builder<Opd>  $query
     */
    public function scopeOfJenis(Builder $query, ?string $jenis): void
    {
        if (filled($jenis)) {
            $query->whereRaw('LOWER(pmo_opds.jenis) = ?', [mb_strtolower(trim($jenis))]);
        }
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
