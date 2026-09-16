<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\FoContact;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Imports funding-officer funnel data from database/seeders/funnel/funnelnew.csv, the
 * single consolidated export that replaces the older two-file (funnelcontactpic.csv +
 * funnelallcontact.csv) merge this seeder used to do.
 *
 * The sheet's tail columns (Status, Proyeksi Bulan/Nilai Closing, Status Closing, Tgl
 * Closing, Nominal Investasi, jangka Waktu, Bagi Hasil, Tier, Bulan Cohort) aren't
 * reliably aligned row-to-row — leftover manual spreadsheet edits occasionally shift a
 * value into the wrong column (e.g. a "Tier n (%)" string landing in "Status Closing").
 * extractClosingInfo() sniffs those fields by value pattern rather than fixed index to
 * stay correct despite the drift.
 *
 * This is a developer-provided data migration, not organic FO activity: every run
 * truncates the funnel/funding tables it owns first (fo_funnel_activity_logs,
 * fo_funnel_activities, fo_funnels, fo_contacts — nothing else writes to these tables)
 * so the seeder is idempotent and safe to re-run without `migrate:fresh`. Each run also
 * logs a note (console + Laravel log) flagging the import as a developer migration so
 * it's clear in retrospect where the data came from.
 *
 * Part of the default DatabaseSeeder run (after FundingOfficerSeeder, since it needs the
 * FO employee records to already exist). Depends on `Employee.user_id` matching the
 * CSV's "User ID" column (31, 33, 47, 57).
 */
class FoFunnelDataSeeder extends Seeder
{
    private const MONTHS = [
        'jan' => 1, 'januari' => 1,
        'feb' => 2, 'febr' => 2, 'februari' => 2,
        'mar' => 3, 'maret' => 3,
        'apr' => 4, 'april' => 4,
        'mei' => 5, 'may' => 5,
        'jun' => 6, 'juni' => 6,
        'jul' => 7, 'juli' => 7,
        'agu' => 8, 'agustus' => 8, 'aug' => 8,
        'sep' => 9, 'september' => 9,
        'okt' => 10, 'oktober' => 10, 'oct' => 10,
        'nov' => 11, 'november' => 11,
        'des' => 12, 'desember' => 12, 'dec' => 12,
    ];

    private const LOST_KEYWORDS = [
        'tidak tertarik', 'tdk tertarik', 'belum tertarik', 'blm tertarik',
        'tidak berminat', 'tdk ada respon', 'tidak ada respon', 'nomor tdk aktif',
        'nomor tidak aktif', 'pindah ke',
    ];

    /**
     * Tables this seeder owns exclusively — safe to truncate on every run since nothing
     * else writes to them. Order matters for the FK chain even though TRUNCATE ... CASCADE
     * handles it regardless (child-most first for readability).
     *
     * @var array<int, string>
     */
    private const OWNED_TABLES = [
        'fo_funnel_activity_logs',
        'fo_funnel_activities',
        'fo_funnels',
        'fo_contacts',
    ];

    public function __construct(private readonly ?string $csvPath = null) {}

    public function run(): void
    {
        $path = $this->csvPath ?? database_path('seeders/funnel/funnelnew.csv');

        if (! file_exists($path)) {
            $this->command?->error("CSV tidak ditemukan: {$path}");

            return;
        }

        $this->truncateOwnedTables();

        $created = 0;
        $skipped = 0;

        $handle = fopen($path, 'r');
        fgetcsv($handle); // header (baris pertama, multi-line tapi tetap 1 baris logis untuk fgetcsv)

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            try {
                $this->importRow($row) ? $created++ : $skipped++;
            } catch (Throwable $e) {
                $skipped++;
                $this->command?->warn('Baris dilewati: '.$e->getMessage());
            }
        }

        fclose($handle);

        $message = "[Migrasi data developer] Import funnelnew.csv selesai: {$created} funnel investor dibuat, {$skipped} baris dilewati.";
        $this->command?->info($message);
        Log::info($message, ['source' => 'FoFunnelDataSeeder', 'csv' => basename($path)]);
    }

    /**
     * Wipes the funnel/funding tables this seeder exclusively owns before re-importing, so
     * the seeder can be re-run on its own (e.g. after a fresh funnelnew.csv from the
     * developer) without needing `php artisan migrate:fresh --seed`.
     */
    private function truncateOwnedTables(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (self::OWNED_TABLES as $table) {
            DB::table($table)->truncate();
        }

        Schema::enableForeignKeyConstraints();

        $message = '[Migrasi data developer] Tabel funnel & funding investor (fo_contacts, fo_funnels, fo_funnel_activities, fo_funnel_activity_logs) di-truncate sebelum import ulang dari funnelnew.csv.';
        $this->command?->warn($message);
        Log::info($message, ['source' => 'FoFunnelDataSeeder', 'tables' => self::OWNED_TABLES]);
    }

    private function importRow(array $row): bool
    {
        $userId = (int) trim($row[0] ?? '');
        $nama = trim($row[1] ?? '');
        $profesi = trim($row[2] ?? '') ?: null;
        [$noTelp, $contactNote] = $this->parseContactMethod($row[3] ?? '');
        $sumber = $this->normalizeSumber($row[4] ?? '');

        if (! $userId || $nama === '') {
            return false;
        }

        $employee = Employee::where('user_id', $userId)->first();

        if (! $employee) {
            return false;
        }

        $contact = FoContact::firstOrCreate(
            ['employee_id' => $employee->id, 'nama' => $nama],
            ['profesi' => $profesi, 'no_telepon' => $noTelp, 'catatan' => $contactNote],
        );

        $tglLeads = $this->parseHariBulanTahun($row[5] ?? '', $row[6] ?? '');
        $qualified = strtolower(trim($row[8] ?? ''));
        $tglQualified = $this->parseTanggalBebas($row[9] ?? '');
        $tglPitch = $this->parseTanggalBebas($row[10] ?? '');
        $totalSesiPitching = (int) trim($row[11] ?? '0');
        $catatanPitching = trim($row[12] ?? '') ?: null;

        $fuDates = [];
        foreach (range(13, 27) as $index) {
            $parsed = $this->parseTanggalBebas($row[$index] ?? '');

            if ($parsed) {
                $fuDates[] = $parsed;
            }
        }

        $catatanFollowUp = trim($row[29] ?? '') ?: null;
        $statusText = trim($row[30] ?? '');

        $closing = $this->extractClosingInfo(array_slice($row, 30));

        $combinedNotes = strtolower($statusText.' '.$catatanPitching.' '.$catatanFollowUp);
        $isLost = $this->containsLostKeyword($combinedNotes);

        $stage = match (true) {
            $closing['is_closing'] => 'won',
            $isLost => 'lost',
            count($fuDates) > 0 => 'followup',
            $totalSesiPitching > 0 || $tglPitch => 'pitching',
            $qualified === 'ya' => 'ql',
            default => 'leads',
        };

        $funnel = FoFunnel::create([
            'fo_contact_id' => $contact->id,
            'employee_id' => $employee->id,
            'stage' => $stage,
            'sumber' => $sumber,
            'tgl_leads' => $tglLeads,
            'tgl_qualified' => $tglQualified,
            'proyeksi_close_date' => $closing['tgl_closing'],
            'proyeksi_amount' => $closing['nominal'],
            'status_closing' => $closing['is_closing'] ? 'Closing' : null,
            'tgl_closing' => $closing['tgl_closing'],
            'nominal_investasi' => $closing['nominal'],
            'jangka_waktu' => $closing['jangka_waktu'],
            'bagi_hasil' => $closing['bagi_hasil'],
            'tier' => $closing['tier'],
        ]);

        if ($tglPitch || $catatanPitching) {
            FoFunnelActivity::create([
                'fo_funnel_id' => $funnel->id,
                'employee_id' => $employee->id,
                'stage' => 'pitching',
                'tanggal' => $tglPitch ?? $tglLeads ?? now()->toDateString(),
                'catatan' => $catatanPitching,
            ]);
        }

        foreach ($fuDates as $i => $date) {
            FoFunnelActivity::create([
                'fo_funnel_id' => $funnel->id,
                'employee_id' => $employee->id,
                'stage' => 'followup',
                'tanggal' => $date,
                'catatan' => $i === count($fuDates) - 1 ? $catatanFollowUp : null,
            ]);
        }

        return true;
    }

    private function containsLostKeyword(string $haystack): bool
    {
        foreach (self::LOST_KEYWORDS as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A handful of source rows put an Instagram handle, email address, or other non-phone
     * text in the "No. Telp" column instead of a phone number — not valid for
     * fo_contacts.no_telepon (varchar 30, meant to hold an actual phone number), so route it
     * into the contact's catatan instead of dropping it or failing the whole row over it.
     *
     * @return array{0: ?string, 1: ?string} [no_telepon, catatan]
     */
    private function parseContactMethod(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return [null, null];
        }

        $looksLikePhone = preg_match('/^[0-9+\-\s()\/]+$/', $value) === 1;

        if ($looksLikePhone && strlen($value) <= 30) {
            return [$value, null];
        }

        $note = match (true) {
            str_starts_with($value, '@') => "IG: {$value}",
            str_contains($value, '@') => "Email: {$value}",
            default => $value,
        };

        return [null, $note];
    }

    private function normalizeSumber(string $value): string
    {
        $lower = strtolower(trim($value));

        return match (true) {
            str_contains($lower, 'expfo') => 'EXPFO',
            str_contains($lower, 'ref') => 'Referral', // menangkap typo sumber "Refferal"
            str_contains($lower, 'sids') => 'SIDS',
            default => 'Murni',
        };
    }

    private function parseHariBulanTahun(string $hari, string $bulanTahun): ?string
    {
        $hari = trim($hari);
        [$month, $year] = $this->parseBulanTahun($bulanTahun);

        if (! $month) {
            return null;
        }

        $day = $hari !== '' && is_numeric($hari) ? (int) $hari : 1;

        return $this->safeDate($year, $month, $day);
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function parseBulanTahun(string $text): array
    {
        if (preg_match('/^([A-Za-z]+)-(\d{2,4})$/', trim($text), $m)) {
            $key = strtolower($m[1]);
            $year = strlen($m[2]) === 2 ? 2000 + (int) $m[2] : (int) $m[2];

            if (isset(self::MONTHS[$key])) {
                return [self::MONTHS[$key], $year];
            }
        }

        return [null, null];
    }

    private function parseTanggalBebas(string $text): ?string
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $text, $m)) {
            return $this->safeDate((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        if (preg_match('/^(\d{1,2})\s+([A-Za-z]+)\.?$/u', $text, $m)) {
            $key = strtolower($m[2]);

            if (isset(self::MONTHS[$key])) {
                return $this->safeDate(2026, self::MONTHS[$key], (int) $m[1]);
            }
        }

        return null;
    }

    private function safeDate(?int $year, ?int $month, ?int $day): ?string
    {
        if (! $year || ! $month || ! $day || $day < 1) {
            return null;
        }

        try {
            $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

            return Carbon::create($year, $month, min($day, $daysInMonth))->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Sniff the closing-status tail columns by value pattern rather than fixed index —
     * the source sheet's later columns aren't reliably aligned row-to-row.
     *
     * @return array{is_closing: bool, tgl_closing: ?string, nominal: ?float, jangka_waktu: ?string, bagi_hasil: ?string, tier: ?string}
     */
    private function extractClosingInfo(array $tailFields): array
    {
        $isClosing = false;
        $tglClosing = null;
        $nominal = null;
        $jangkaWaktu = null;
        $bagiHasil = null;
        $tier = null;

        foreach ($tailFields as $value) {
            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            if (strtolower($value) === 'closing') {
                $isClosing = true;

                continue;
            }

            if (preg_match('/^tier/i', $value)) {
                $tier = $value;

                continue;
            }

            if (str_contains($value, '%')) {
                $bagiHasil = $value;

                continue;
            }

            if (preg_match('/bulan/i', $value)) {
                $jangkaWaktu = $value;

                continue;
            }

            if (preg_match('/^[\d.,]+$/', $value)) {
                $nominal = (float) str_replace(',', '', $value);

                continue;
            }

            $date = $this->parseTanggalBebas($value) ?? $this->parseSlashDate($value);

            if ($date) {
                $tglClosing = $date;
            }
        }

        return [
            'is_closing' => $isClosing,
            'tgl_closing' => $tglClosing,
            'nominal' => $nominal,
            'jangka_waktu' => $jangkaWaktu,
            'bagi_hasil' => $bagiHasil,
            'tier' => $tier,
        ];
    }

    private function parseSlashDate(string $text): ?string
    {
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', trim($text), $m)) {
            return $this->safeDate((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }
}
