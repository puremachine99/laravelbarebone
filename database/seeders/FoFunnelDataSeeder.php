<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\FoContact;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Imports funding-officer funnel data by merging two source exports:
 *
 * - database/seeders/funnel/funnelcontactpic.csv is the more complete contact
 *   list (470 rows, clean "Follow up 1..12" dates in a consistent d-M-Y
 *   format) but has no closing-outcome columns.
 * - database/seeders/funnel/funnelallcontact.csv has fewer/staler contacts
 *   (397 rows) but is the only source with closing outcome data (status,
 *   date, nominal, tenor, bagi hasil, tier) in its tail columns, and those
 *   tail columns aren't reliably aligned row-to-row (leftover from manual
 *   spreadsheet editing).
 *
 * The pic file drives the import since it's the superset of contacts. For
 * each of its rows we pop a same-named row queued from the allcontact file
 * (FIFO per lowercase name — both files carry duplicate names per FO from
 * manual edits, and a handful of contacts were reassigned to a different FO
 * between exports, so matching on name alone catches more real pairs than
 * matching on FO+name) and, if found, overlay its closing info. Any
 * allcontact rows never matched (~4 contacts not present in the pic export)
 * are imported afterwards via the original allcontact-only parsing.
 *
 * Part of the default DatabaseSeeder run (after FundingOfficerSeeder, since it
 * needs the FO employee records to already exist). Depends on `Employee.user_id`
 * matching the CSVs' FO ID / userid columns (31, 33, 47, 57).
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

    public function run(): void
    {
        $picPath = database_path('seeders/funnel/funnelcontactpic.csv');
        $legacyPath = database_path('seeders/funnel/funnelallcontact.csv');

        if (! file_exists($picPath) || ! file_exists($legacyPath)) {
            $this->command?->error("CSV tidak ditemukan: {$picPath} / {$legacyPath}");

            return;
        }

        $closingQueue = $this->loadClosingQueue($legacyPath);

        $created = 0;
        $skipped = 0;

        $handle = fopen($picPath, 'r');
        fgetcsv($handle); // header (baris pertama, multi-line tapi tetap 1 baris logis untuk fgetcsv)

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            try {
                $this->importPicRow($row, $closingQueue) ? $created++ : $skipped++;
            } catch (Throwable $e) {
                $skipped++;
                $this->command?->warn('Baris dilewati: '.$e->getMessage());
            }
        }

        fclose($handle);

        foreach ($closingQueue as $queue) {
            foreach ($queue as $legacyRow) {
                try {
                    $this->importLegacyRow($legacyRow) ? $created++ : $skipped++;
                } catch (Throwable $e) {
                    $skipped++;
                    $this->command?->warn('Baris (legacy) dilewati: '.$e->getMessage());
                }
            }
        }

        $this->command?->info("Selesai. {$created} funnel dibuat, {$skipped} baris dilewati.");
    }

    /**
     * Groups every allcontact.csv row by lowercase nasabah name, preserving row order so
     * duplicate names are paired FIFO with the pic export.
     *
     * @return array<string, array<int, array<int, string>>>
     */
    private function loadClosingQueue(string $path): array
    {
        $handle = fopen($path, 'r');
        fgetcsv($handle);

        $queue = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $nama = trim($row[1] ?? '');

            if ($nama === '') {
                continue;
            }

            $queue[strtolower($nama)][] = $row;
        }

        fclose($handle);

        return $queue;
    }

    /**
     * @param  array<string, array<int, array<int, string>>>  $closingQueue
     */
    private function importPicRow(array $row, array &$closingQueue): bool
    {
        $userId = (int) trim($row[0] ?? '');
        $nama = trim($row[1] ?? '');
        $profesi = trim($row[2] ?? '') ?: null;
        $noTelp = $this->sanitizePhone($row[3] ?? '');
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
            ['profesi' => $profesi, 'no_telepon' => $noTelp],
        );

        $tglLeads = $this->parseDmy($row[5] ?? '');
        $qualified = strtolower(trim($row[6] ?? ''));
        $tglQualified = $this->parseDmy($row[8] ?? '') ?? $this->parseDmy($row[7] ?? '');
        $tglPitch = $this->parseDmy($row[9] ?? '');
        $totalSesiPitching = (int) trim($row[10] ?? '0');
        $catatanPitching = trim($row[11] ?? '') ?: null;

        $fuDates = [];
        foreach (range(12, 23) as $index) {
            $parsed = $this->parseDmy($row[$index] ?? '');

            if ($parsed) {
                $fuDates[] = $parsed;
            }
        }

        $legacyRow = null;
        $key = strtolower($nama);

        if (! empty($closingQueue[$key])) {
            $legacyRow = array_shift($closingQueue[$key]);

            if (empty($closingQueue[$key])) {
                unset($closingQueue[$key]);
            }
        }

        $closing = $legacyRow
            ? $this->extractClosingInfo(array_slice($legacyRow, 20))
            : ['is_closing' => false, 'tgl_closing' => null, 'nominal' => null, 'jangka_waktu' => null, 'bagi_hasil' => null, 'tier' => null];

        $legacyStatusText = $legacyRow ? trim($legacyRow[20] ?? '') : '';
        $legacyCatatanFollowUp = $legacyRow ? trim($legacyRow[19] ?? '') : '';

        $combinedNotes = strtolower($legacyStatusText.' '.$catatanPitching.' '.$legacyCatatanFollowUp);
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
                'catatan' => $i === count($fuDates) - 1 ? ($legacyCatatanFollowUp ?: null) : null,
            ]);
        }

        return true;
    }

    /**
     * Handles rows from funnelallcontact.csv that had no same-named counterpart in the pic
     * export (contacts absent from the newer/more complete list).
     */
    private function importLegacyRow(array $row): bool
    {
        $userId = (int) trim($row[0] ?? '');
        $nama = trim($row[1] ?? '');
        $profesi = trim($row[2] ?? '') ?: null;
        $noTelp = $this->sanitizePhone($row[3] ?? '');
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
            ['profesi' => $profesi, 'no_telepon' => $noTelp],
        );

        $tglLeads = $this->parseHariBulanTahun($row[5] ?? '', $row[6] ?? '');
        $qualified = strtolower(trim($row[7] ?? ''));
        $tglQualified = $this->parseTanggalBebas($row[8] ?? '');
        $totalSesiPitching = (int) trim($row[11] ?? '0');
        $catatanPitching = trim($row[12] ?? '') ?: null;
        $tglPitch = $this->parsePitchDate($row[9] ?? '', $row[10] ?? '');

        $fuDates = [];
        foreach ([13, 14, 15, 16, 17] as $index) {
            $parsed = $this->parseTanggalBebas($row[$index] ?? '');

            if ($parsed) {
                $fuDates[] = $parsed;
            }
        }

        $catatanFollowUp = trim($row[19] ?? '') ?: null;
        $statusText = trim($row[20] ?? '');

        $closing = $this->extractClosingInfo(array_slice($row, 20));

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
     * A handful of source rows have an email address in the "No. Telp" column instead of a
     * phone number — too long for fo_contacts.no_telepon (varchar 30) and not a phone number
     * anyway, so drop it rather than fail the whole contact/funnel import over it.
     */
    private function sanitizePhone(string $value): ?string
    {
        $value = trim($value);

        if ($value === '' || str_contains($value, '@') || strlen($value) > 30) {
            return null;
        }

        return $value;
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

    /**
     * Parses the pic export's clean, consistent "d-M-Y" dates (e.g. "12-Jun-2026"), used for
     * every date column in funnelcontactpic.csv. Empty cells and "-" placeholders mean unset.
     */
    private function parseDmy(string $text): ?string
    {
        $text = trim($text);

        if ($text === '' || $text === '-') {
            return null;
        }

        try {
            return Carbon::createFromFormat('d-M-Y', $text)->toDateString();
        } catch (Throwable) {
            return null;
        }
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

    private function parsePitchDate(string $dayOrDate, string $bulanTahun): ?string
    {
        $dayOrDate = trim($dayOrDate);

        if ($dayOrDate === '') {
            return null;
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $dayOrDate, $m)) {
            return $this->safeDate((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        if (is_numeric($dayOrDate)) {
            [$month, $year] = $this->parseBulanTahun($bulanTahun);

            if ($month) {
                return $this->safeDate($year, $month, (int) $dayOrDate);
            }
        }

        return null;
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
