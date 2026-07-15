<?php

namespace App\Models;

use Database\Factories\FoFunnelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class FoFunnel extends Model
{
    /** @use HasFactory<FoFunnelFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'tgl_leads' => 'date',
        'tgl_qualified' => 'date',
        'proyeksi_close_date' => 'date',
        'tgl_closing' => 'date',
        'proyeksi_amount' => 'decimal:2',
        'nominal_investasi' => 'decimal:2',
    ];

    public function contact()
    {
        return $this->belongsTo(FoContact::class, 'fo_contact_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function activities()
    {
        return $this->hasMany(FoFunnelActivity::class);
    }

    public function dokumenAkadUrl(): ?string
    {
        if (! $this->dokumen_akad_path) {
            return null;
        }

        return Storage::disk('s3')->url($this->dokumen_akad_path);
    }
}
