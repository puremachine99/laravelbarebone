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

    public function regency()
    {
        return $this->belongsTo(Regency::class);
    }

    public function urusans()
    {
        return $this->belongsToMany(Urusan::class, 'pmo_opd_urusan', 'opd_id', 'urusan_id');
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
