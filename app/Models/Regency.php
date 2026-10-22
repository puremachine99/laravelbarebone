<?php

namespace App\Models;

use App\Enums\OpdJenis;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Regency extends Model
{
    use HasFactory;

    /**
     * Type of the per-province placeholder regency (e.g. "Prov. Jawa Timur") that holds the province-level OPDs.
     */
    public const TYPE_PROVINCE = 'Provinsi';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Every instansi in this kabupaten/kota, whatever its jenis.
     *
     * @return HasMany<Opd, $this>
     */
    public function opds(): HasMany
    {
        return $this->hasMany(Opd::class);
    }

    /**
     * @return HasMany<Opd, $this>
     */
    public function pemdaOpds(): HasMany
    {
        return $this->opds()->where('pmo_opds.jenis', OpdJenis::Opd->value);
    }

    /**
     * @return HasMany<Opd, $this>
     */
    public function unitKesehatans(): HasMany
    {
        return $this->opds()->where('pmo_opds.jenis', OpdJenis::UnitKesehatan->value);
    }

    /**
     * @return HasMany<Opd, $this>
     */
    public function pendidikans(): HasMany
    {
        return $this->opds()->where('pmo_opds.jenis', OpdJenis::Pendidikan->value);
    }
}
