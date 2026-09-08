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

    /**
     * Pipeline stage progression order, used to check whether a funnel's current stage
     * is still at or past a given milestone (so achievement metrics stay in sync with
     * the current stage instead of a stale "ever reached" marker).
     *
     * @var array<string, int>
     */
    public const STAGE_ORDER = [
        'leads' => 0,
        'ql' => 1,
        'pitching' => 2,
        'followup' => 3,
        'won' => 4,
        'lost' => 4,
        'closing' => 5,
        'akad' => 6,
    ];

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
        'dokumen_akad_files' => 'array',
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

    public function scopeStageAtLeast($query, string $stage)
    {
        $minRank = self::STAGE_ORDER[$stage];

        $qualifyingStages = collect(self::STAGE_ORDER)
            ->filter(fn ($rank) => $rank >= $minRank)
            ->keys()
            ->all();

        return $query->whereIn('stage', $qualifyingStages);
    }

    /**
     * SQL expression for "the date that actually matters for this funnel right now" —
     * proyeksi_close_date is only a projection and is null for most rows, so filtering
     * the whole board by it alone (regardless of stage) hides almost everything.
     *
     * Must branch on the current `stage`, not just pick whichever date column happens to
     * be non-null: legacy imported rows can have tgl_closing populated (from the original
     * CSV's closing info) even though the record's current stage is still 'won', not
     * 'closing' — a plain COALESCE would misfile those under their stale tgl_closing month
     * instead of the tgl_won month the other reports (Monthly Conversion/Achievement,
     * Management FO) use for the same rows.
     */
    public static function relevantDateExpression(string $driver): string
    {
        return "COALESCE(
            CASE
                WHEN stage = 'won' THEN tgl_won
                WHEN stage IN ('lost', 'closing', 'akad') THEN tgl_closing
                WHEN stage = 'ql' THEN tgl_qualified
                WHEN stage IN ('pitching', 'followup') THEN tgl_qualified
                ELSE NULL
            END,
            tgl_leads
        )";
    }

    public function scopeWhereRelevantMonth($query, int $month)
    {
        $driver = $query->getConnection()->getDriverName();
        $date = self::relevantDateExpression($driver);
        $expr = $driver === 'sqlite'
            ? "CAST(strftime('%m', {$date}) AS INTEGER)"
            : "EXTRACT(MONTH FROM {$date})";

        return $query->whereRaw("{$expr} = ?", [$month]);
    }

    public function scopeWhereRelevantYear($query, int $year)
    {
        $driver = $query->getConnection()->getDriverName();
        $date = self::relevantDateExpression($driver);
        $expr = $driver === 'sqlite'
            ? "CAST(strftime('%Y', {$date}) AS INTEGER)"
            : "EXTRACT(YEAR FROM {$date})";

        return $query->whereRaw("{$expr} = ?", [$year]);
    }

    public function dokumenAkadUrl(): ?string
    {
        if (! $this->dokumen_akad_path) {
            return null;
        }

        return Storage::disk('s3')->temporaryUrl($this->dokumen_akad_path, now()->addMinutes(15));
    }

    /**
     * Dokumen akad ber-tag buat ditampilkan (Kanban card, tabel). Data lama yang cuma punya satu
     * dokumen (kolom dokumen_akad_path/nama, sebelum dokumen_akad_files ada) tetap muncul sebagai
     * satu entri tanpa tag.
     *
     * @return array<int, array{tag: ?string, nama: ?string, url: string}>
     */
    public function dokumenAkadDocuments(): array
    {
        if ($this->dokumen_akad_files) {
            return collect($this->dokumen_akad_files)
                ->map(fn (array $document) => [
                    'tag' => $document['tag'] ?? null,
                    'nama' => $document['nama'] ?? null,
                    'url' => Storage::disk('s3')->temporaryUrl($document['path'], now()->addMinutes(15)),
                ])
                ->all();
        }

        if ($this->dokumen_akad_path) {
            return [[
                'tag' => null,
                'nama' => $this->dokumen_akad_nama,
                'url' => $this->dokumenAkadUrl(),
            ]];
        }

        return [];
    }
}
