<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Province extends Model
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
     * Instansi in any kabupaten/kota of this province, including the province-level ("Provinsi") regency.
     *
     * @return HasManyThrough<Opd, Regency, $this>
     */
    public function opds(): HasManyThrough
    {
        return $this->hasManyThrough(Opd::class, Regency::class);
    }
}
