<?php

namespace App\Models;

use App\Enums\OpdJenis;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Region extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return HasMany<Regency, $this>
     */
    public function regencies(): HasMany
    {
        return $this->hasMany(Regency::class);
    }

    /**
     * Kementerian / Lembaga, which belong to a region directly instead of a kabupaten/kota.
     *
     * @return HasMany<Opd, $this>
     */
    public function kementerians(): HasMany
    {
        return $this->hasMany(Opd::class)->where('pmo_opds.jenis', OpdJenis::Kementerian->value);
    }

    /**
     * Instansi located in this region's kabupaten/kota (every jenis except Kementerian).
     *
     * @return HasManyThrough<Opd, Regency, $this>
     */
    public function regencyOpds(): HasManyThrough
    {
        return $this->hasManyThrough(Opd::class, Regency::class);
    }
}
