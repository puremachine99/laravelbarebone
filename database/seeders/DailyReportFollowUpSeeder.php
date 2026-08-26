<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\FoContact;
use App\Models\FoFunnel;
use App\Models\FoFunnelActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Matches database/seeders/funnel/dailyreport.csv (a raw, per-visit activity log: FO id,
 * tanggal, nama investor, status, hasil kegiatan) against the FoFunnelActivity records that
 * FoFunnelDataSeeder already created from funnelcontactpic.csv's "Follow up 1..12" date
 * columns, and fills in each activity's real note from the daily report.
 *
 * dailyreport.csv has no contact id — only a free-text name per FO — and that name is
 * frequently a shorthand, alias, or has an extra descriptor compared to the contact name
 * funnelcontactpic.csv produced (e.g. "Ica HIPMI" vs "Ica", "Reynaldi Hipmi" vs
 * "Reynaldi HIPMi", "CIMB (Asti)" vs "CIMB"). Matching therefore happens per FO in two steps:
 *   1. Fuzzy-match the daily report name against that FO's FoContact names (normalized,
 *      honorifics stripped, parenthetical aliases tried separately). Below MIN_NAME_SCORE the
 *      row is treated as unmatched and skipped rather than guessed.
 *   2. Within the matched contact's funnel(s), look for an existing FoFunnelActivity dated
 *      exactly on the report's tanggal. If one exists, its (often-empty) catatan is overwritten
 *      with the report's "Hasil Kegiatan" (status-tagged). If none exists, a new followup
 *      activity is created for it — the report captured a real visit the aggregate FU-date
 *      columns in funnelcontactpic.csv missed.
 *
 * Must run after FoFunnelDataSeeder, which is what populates the FoContact/FoFunnel/
 * FoFunnelActivity rows this seeder matches against.
 */
class DailyReportFollowUpSeeder extends Seeder
{
    private const MIN_NAME_SCORE = 55.0;

    /**
     * Candidates scoring within this many points of the top name-score are treated as a
     * near-tie and disambiguated by date overlap instead of the raw score order.
     */
    private const TIE_MARGIN = 10.0;

    private const HONORIFICS = [
        'dr', 'drg', 'pak', 'bu', 'ibu', 'bapak', 'prof', 'mas', 'mbak', 'mba', 'sdr', 'sdri',
    ];

    /** @var array<int, ?Employee> */
    private array $employeeCache = [];

    /**
     * @var array<int, array<int, array{contact: FoContact, variants: array<int, string>}>>
     *                                                                                      keyed by employee_id
     */
    private array $contactsCache = [];

    /** @var array<int, Collection<int, FoFunnel>> keyed by fo_contact_id */
    private array $funnelsCache = [];

    public function run(): void
    {
        $path = database_path('seeders/funnel/dailyreport.csv');

        if (! file_exists($path)) {
            $this->command?->error("CSV tidak ditemukan: {$path}");

            return;
        }

        $updated = 0;
        $created = 0;
        $skippedInvalid = 0;
        $skippedNoEmployee = 0;
        $skippedNoContactMatch = 0;

        $handle = fopen($path, 'r');
        fgetcsv($handle); // header

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $userId = (int) trim($row[0] ?? '');
            $tanggal = $this->parseDmyDash($row[1] ?? '');
            $nama = trim($row[2] ?? '');
            $status = trim($row[3] ?? '');
            $hasilKegiatan = trim($row[4] ?? '');

            if (! $userId || $nama === '' || ! $tanggal) {
                $skippedInvalid++;

                continue;
            }

            $employee = $this->resolveEmployee($userId);

            if (! $employee) {
                $skippedNoEmployee++;

                continue;
            }

            $contact = $this->matchContact($employee, $nama, $tanggal);

            if (! $contact) {
                $skippedNoContactMatch++;
                $this->command?->warn("Tidak ada kontak yg cocok utk FO {$userId}: \"{$nama}\"");

                continue;
            }

            try {
                $this->applyToFunnel($employee, $contact, $tanggal, $status, $hasilKegiatan)
                    ? $updated++
                    : $created++;
            } catch (Throwable $e) {
                $skippedInvalid++;
                $this->command?->warn('Baris dilewati: '.$e->getMessage());
            }
        }

        fclose($handle);

        $this->command?->info(
            "Selesai. {$updated} activity diupdate, {$created} activity baru dibuat, "
            ."{$skippedNoContactMatch} baris tanpa kontak yg cocok, "
            ."{$skippedNoEmployee} baris tanpa FO, {$skippedInvalid} baris tidak valid."
        );
    }

    private function resolveEmployee(int $userId): ?Employee
    {
        if (! array_key_exists($userId, $this->employeeCache)) {
            $this->employeeCache[$userId] = Employee::where('user_id', $userId)->first();
        }

        return $this->employeeCache[$userId];
    }

    /**
     * @return array<int, array{contact: FoContact, variants: array<int, string>}>
     */
    private function contactsForEmployee(int $employeeId): array
    {
        if (! isset($this->contactsCache[$employeeId])) {
            $this->contactsCache[$employeeId] = FoContact::where('employee_id', $employeeId)
                ->get(['id', 'nama'])
                ->map(fn (FoContact $contact) => [
                    'contact' => $contact,
                    'variants' => $this->normalizedVariants($contact->nama),
                ])
                ->all();
        }

        return $this->contactsCache[$employeeId];
    }

    /**
     * Name score alone leaves real near-ties on the table — e.g. "Jatim Syariah" scores
     * almost identically against two different Jatim Syariah contacts, or a duplicate
     * "Kiki" contact row scores identically against another. In those cases, whichever
     * candidate actually has a follow-up activity dated on this report row's tanggal is
     * much stronger evidence than the last couple of similarity points, so it breaks the
     * tie. It is deliberately NOT used as an independent score: on its own, date overlap is
     * mostly coincidence (many unrelated contacts get touched on the same day), and plenty
     * of genuinely correct matches have zero date overlap at all — dailyreport.csv logs
     * every visit while funnelcontactpic.csv's FU-date columns are a separate, curated
     * subset of checkpoints, not the same list of dates.
     */
    private function matchContact(Employee $employee, string $rawName, string $tanggal): ?FoContact
    {
        $reportVariants = $this->normalizedVariants($rawName);

        if (empty($reportVariants)) {
            return null;
        }

        $scored = [];

        foreach ($this->contactsForEmployee($employee->id) as $candidate) {
            $score = 0.0;

            foreach ($reportVariants as $reportVariant) {
                foreach ($candidate['variants'] as $candidateVariant) {
                    $score = max($score, $this->nameSimilarity($reportVariant, $candidateVariant));
                }
            }

            if ($score >= self::MIN_NAME_SCORE) {
                $scored[] = ['contact' => $candidate['contact'], 'score' => $score];
            }
        }

        if (empty($scored)) {
            return null;
        }

        $topScore = max(array_column($scored, 'score'));
        $contenders = array_values(array_filter(
            $scored,
            fn (array $c) => $c['score'] >= $topScore - self::TIE_MARGIN
        ));

        if (count($contenders) === 1) {
            return $contenders[0]['contact'];
        }

        $withDateMatch = array_values(array_filter(
            $contenders,
            fn (array $c) => $this->hasActivityOnDate($c['contact'], $tanggal)
        ));

        if (count($withDateMatch) === 1) {
            return $withDateMatch[0]['contact'];
        }

        usort($contenders, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return $contenders[0]['contact'];
    }

    private function hasActivityOnDate(FoContact $contact, string $tanggal): bool
    {
        foreach ($this->funnelsForContact($contact) as $funnel) {
            foreach ($funnel->activities as $activity) {
                if ($activity->tanggal?->toDateString() === $tanggal) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return Collection<int, FoFunnel>
     */
    private function funnelsForContact(FoContact $contact): Collection
    {
        if (! isset($this->funnelsCache[$contact->id])) {
            $this->funnelsCache[$contact->id] = FoFunnel::where('fo_contact_id', $contact->id)
                ->with('activities')
                ->get();
        }

        return $this->funnelsCache[$contact->id];
    }

    /**
     * Applies one dailyreport row to the matched contact's funnel(s). Returns true when an
     * existing activity was updated, false when a new one was created.
     */
    private function applyToFunnel(
        Employee $employee,
        FoContact $contact,
        string $tanggal,
        string $status,
        string $hasilKegiatan
    ): bool {
        $funnels = $this->funnelsForContact($contact);

        if ($funnels->isEmpty()) {
            return false;
        }

        $catatan = $this->buildCatatan($status, $hasilKegiatan);

        foreach ($funnels as $funnel) {
            $activity = $funnel->activities->first(
                fn (FoFunnelActivity $a) => $a->tanggal?->toDateString() === $tanggal
            );

            if ($activity) {
                if ($catatan !== '') {
                    $activity->update(['catatan' => $catatan]);
                }

                return true;
            }
        }

        if ($catatan === '') {
            return false;
        }

        $funnel = $this->pickFunnelForDate($funnels, $tanggal);

        $activity = FoFunnelActivity::create([
            'fo_funnel_id' => $funnel->id,
            'employee_id' => $employee->id,
            'stage' => 'followup',
            'tanggal' => $tanggal,
            'catatan' => $catatan,
        ]);

        $funnel->setRelation('activities', $funnel->activities->push($activity));

        return false;
    }

    private function buildCatatan(string $status, string $hasilKegiatan): string
    {
        $status = trim($status);
        $hasilKegiatan = trim($hasilKegiatan);

        if ($status === '') {
            return $hasilKegiatan;
        }

        $tag = '['.ucfirst(strtolower($status)).']';

        return $hasilKegiatan === '' ? $tag : "{$tag} {$hasilKegiatan}";
    }

    /**
     * @param  Collection<int, FoFunnel>  $funnels
     */
    private function pickFunnelForDate(Collection $funnels, string $targetDate): FoFunnel
    {
        if ($funnels->count() === 1) {
            return $funnels->first();
        }

        $target = Carbon::parse($targetDate);
        $best = null;
        $bestDiff = null;

        foreach ($funnels as $funnel) {
            foreach ($funnel->activities as $activity) {
                if (! $activity->tanggal) {
                    continue;
                }

                $diff = abs($activity->tanggal->diffInDays($target));

                if ($bestDiff === null || $diff < $bestDiff) {
                    $bestDiff = $diff;
                    $best = $funnel;
                }
            }
        }

        return $best ?? $funnels->sortBy('id')->first();
    }

    /**
     * Parses dailyreport.csv's "d-m-Y" dates (e.g. "23-06-2026"). Empty cells and the "#N/A"
     * placeholder used for a handful of malformed rows mean unset.
     */
    private function parseDmyDash(string $text): ?string
    {
        $text = trim($text);

        if ($text === '' || strtoupper($text) === '#N/A') {
            return null;
        }

        try {
            return Carbon::createFromFormat('d-m-Y', $text)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Builds one or more normalized forms of a name to match against: the name with any
     * parenthetical aside removed, and — when present — the parenthetical content on its own,
     * since a name is sometimes written either way across the two source files
     * (e.g. "Hananta Wiratama (Wira HIPMI)" vs a bare "Wira HIPMI" or "Hananta Wiratama").
     *
     * @return array<int, string>
     */
    private function normalizedVariants(string $raw): array
    {
        $variants = [];

        if (preg_match('/^(.*?)\((.*?)\)(.*)$/', $raw, $m)) {
            $outer = trim($m[1].' '.$m[3]);
            $inner = trim($m[2]);

            if ($outer !== '') {
                $variants[] = $this->normalizeName($outer);
            }

            if ($inner !== '') {
                $variants[] = $this->normalizeName($inner);
            }
        }

        $withoutParens = trim(preg_replace('/\(.*?\)/', '', $raw) ?? $raw);

        if ($withoutParens !== '') {
            $variants[] = $this->normalizeName($withoutParens);
        }

        return array_values(array_unique(array_filter($variants, fn ($v) => $v !== '')));
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/[.,\/\\\\_-]+/', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = trim($name);

        if ($name === '') {
            return '';
        }

        $tokens = array_values(array_filter(
            explode(' ', $name),
            fn ($token) => $token !== '' && ! in_array($token, self::HONORIFICS, true)
        ));

        return implode(' ', $tokens);
    }

    /**
     * Combines substring containment, shared-token overlap, and character-level similarity so
     * that shorthand names ("Ica" for "Ica HIPMI"), reordered/extra-descriptor names
     * ("Alfian Ilham R" for "Alfian Ilham - Nofal"), and near-identical typos
     * ("Reynaldi Hipmi" vs "Reynaldi HIPMi") all score highly, while unrelated short names
     * (e.g. two different single-word entities) don't.
     */
    private function nameSimilarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 100.0;
        }

        if (str_contains($a, $b) || str_contains($b, $a)) {
            $shorter = min(mb_strlen($a), mb_strlen($b));
            $longer = max(mb_strlen($a), mb_strlen($b));

            return 85 + 15 * ($shorter / $longer);
        }

        $tokensA = array_unique(explode(' ', $a));
        $tokensB = array_unique(explode(' ', $b));
        $common = array_intersect($tokensA, $tokensB);

        $tokenScore = count($common) > 0
            ? (count($common) / max(count($tokensA), count($tokensB))) * 100
            : 0.0;

        similar_text($a, $b, $charPercent);

        // With zero shared tokens, two short unrelated names can still score deceptively high
        // on raw character overlap (e.g. "dhimas" vs "thomas" share "h" and the "mas" suffix,
        // ~67%) — only trust char similarity on its own when it's high enough to represent a
        // typo of the same name, not a coincidental resemblance between different names.
        if ($tokenScore === 0.0 && $charPercent < 80.0) {
            return 0.0;
        }

        return max($tokenScore, $charPercent);
    }
}
