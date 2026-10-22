<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The development journal behind Sentry → Jurnal: one markdown file per month in report/, named "{year}{month}.md"
 * (e.g. 2026okt.md, 2026aug.md). Inside, every "## <day>" heading ("7", "5–8") is one timeline entry; any other
 * "## " heading (e.g. "## Catatan") is a note shown under the timeline. Headings inside ``` code fences are ignored.
 */
class DevJournal
{
    /**
     * Accepted file-name month slugs (Indonesian and English).
     *
     * @var array<int, list<string>>
     */
    private const MONTH_SLUGS = [
        1 => ['jan'], 2 => ['feb'], 3 => ['mar'], 4 => ['apr'], 5 => ['mei', 'may'], 6 => ['jun'],
        7 => ['jul'], 8 => ['agu', 'aug'], 9 => ['sep'], 10 => ['okt', 'oct'], 11 => ['nov'], 12 => ['des', 'dec'],
    ];

    /**
     * @var array<int, string>
     */
    public const MONTH_NAMES = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function __construct(private string $directory) {}

    public static function fromReports(): self
    {
        return new self(base_path('report'));
    }

    /**
     * Years that have at least one monthly journal file, newest first.
     *
     * @return list<int>
     */
    public function years(): array
    {
        return collect($this->files())->pluck('year')->unique()->sortDesc()->values()->all();
    }

    /**
     * The twelve months of a year; a month without a file has no entries.
     *
     * @return array<int, array{name: string, title: ?string, entries: list<array{label: string, html: string}>, notes: list<array{label: string, html: string}>}>
     */
    public function months(int $year): array
    {
        $months = [];

        foreach (self::MONTH_NAMES as $number => $name) {
            $months[$number] = ['name' => $name, 'title' => null, 'entries' => [], 'notes' => []];
        }

        foreach ($this->files() as $file) {
            if ($file['year'] === $year) {
                $months[$file['month']] = ['name' => self::MONTH_NAMES[$file['month']], ...$this->parse((string) file_get_contents($file['path']))];
            }
        }

        return $months;
    }

    /**
     * Splits one month's markdown into timeline entries and notes.
     *
     * @return array{title: ?string, entries: list<array{label: string, html: string}>, notes: list<array{label: string, html: string}>}
     */
    public function parse(string $markdown): array
    {
        $title = null;
        $sections = [];
        $current = null;
        $inFence = false;

        foreach (preg_split('/\R/', $markdown) as $line) {
            if (str_starts_with(ltrim($line), '```')) {
                $inFence = ! $inFence;
            }

            if (! $inFence && preg_match('/^# (.+)$/', $line, $match) && $title === null && $current === null) {
                $title = trim($match[1]);

                continue;
            }

            if (! $inFence && preg_match('/^## (.+)$/', $line, $match)) {
                $sections[] = ['label' => trim($match[1]), 'lines' => []];
                $current = array_key_last($sections);

                continue;
            }

            if ($current !== null) {
                $sections[$current]['lines'][] = $line;
            }
        }

        $entries = [];
        $notes = [];

        foreach ($sections as $section) {
            $item = [
                'label' => $section['label'],
                'html' => (string) Str::markdown(trim(implode("\n", $section['lines'])), [
                    'html_input' => 'escape',
                    'allow_unsafe_links' => false,
                ]),
            ];

            if (preg_match('/^\d{1,2}(\s*[–-]\s*\d{1,2})?$/u', $section['label'])) {
                $entries[] = $item;
            } else {
                $notes[] = $item;
            }
        }

        return ['title' => $title, 'entries' => $entries, 'notes' => $notes];
    }

    /**
     * @return list<array{year: int, month: int, path: string}>
     */
    private function files(): array
    {
        $files = [];

        foreach (glob($this->directory.'/*.md') ?: [] as $path) {
            if (! preg_match('/^(\d{4})([a-z]{3})\.md$/', basename($path), $match)) {
                continue;
            }

            foreach (self::MONTH_SLUGS as $month => $slugs) {
                if (in_array($match[2], $slugs, true)) {
                    $files[] = ['year' => (int) $match[1], 'month' => $month, 'path' => $path];
                }
            }
        }

        return $files;
    }
}
