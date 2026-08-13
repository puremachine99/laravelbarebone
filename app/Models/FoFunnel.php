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

    /**
     * Stages where the investor's money has actually landed at the company —
     * 'won' is only a verbal commitment and must not be counted as closing.
     *
     * @var array<int, string>
     */
    public const CLOSED_STAGES = ['closing', 'akad'];

    protected $guarded = ['id'];

    protected $casts = [
        'tgl_leads' => 'date',
        'tgl_qualified' => 'date',
        'proyeksi_close_date' => 'date',
        'tgl_won' => 'date',
        'tgl_closing' => 'date',
        'proyeksi_amount' => 'decimal:2',
        'nominal_won' => 'decimal:2',
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

    public function logs()
    {
        return $this->morphMany(ActivityLog::class, 'loggable')->latest('id');
    }

    public function dokumenAkadUrl(): ?string
    {
        if (! $this->dokumen_akad_path) {
            return null;
        }

        return Storage::disk('s3')->temporaryUrl($this->dokumen_akad_path, now()->addMinutes(15));
    }
}
