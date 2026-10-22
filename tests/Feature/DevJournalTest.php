<?php

use App\Models\User;
use App\Support\DevJournal;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->journalDir = storage_path('framework/testing/dev-journal');
    File::deleteDirectory($this->journalDir);
    File::ensureDirectoryExists($this->journalDir);
});

afterEach(function () {
    File::deleteDirectory($this->journalDir);
});

test('a month file splits into day entries and notes, ignoring headings inside code fences', function () {
    $parsed = (new DevJournal($this->journalDir))->parse(<<<'MD'
        # Report Juli 2026

        ## 7

        - **Migrasi**
          - Command `sync:master`

        ## 5–8

        - Notifikasi

        ## Catatan

        ```
        ## dev server
        php artisan migrate
        ```
        MD);

    expect($parsed['title'])->toBe('Report Juli 2026')
        ->and(collect($parsed['entries'])->pluck('label')->all())->toBe(['7', '5–8'])
        ->and($parsed['entries'][0]['html'])->toContain('<strong>Migrasi</strong>', '<code>sync:master</code>')
        ->and(collect($parsed['notes'])->pluck('label')->all())->toBe(['Catatan'])
        ->and($parsed['notes'][0]['html'])->toContain('## dev server');
});

test('raw html in a journal is escaped, not rendered', function () {
    $parsed = (new DevJournal($this->journalDir))->parse("## 1\n\n- <script>alert(1)</script>");

    expect($parsed['entries'][0]['html'])->not->toContain('<script>');
});

test('files are matched to months by Indonesian or English slug, per year', function () {
    File::put($this->journalDir.'/2026okt.md', "## 22\n\n- Oktober");
    File::put($this->journalDir.'/2026aug.md', "## 3\n\n- Agustus");
    File::put($this->journalDir.'/2025des.md', "## 1\n\n- Desember");
    File::put($this->journalDir.'/sso-plan.md', "## 1\n\n- bukan jurnal");

    $journal = new DevJournal($this->journalDir);
    $months = $journal->months(2026);

    expect($journal->years())->toBe([2026, 2025])
        ->and($months)->toHaveCount(12)
        ->and($months[10]['entries'][0]['label'])->toBe('22')
        ->and($months[8]['entries'][0]['label'])->toBe('3')
        ->and($months[1]['entries'])->toBe([]);
});

test('Sentry has a Jurnal tab after Force Login that renders the monthly reports as a timeline', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('Super Admin'));

    $this->actingAs($superAdmin)->get(route('dev.errors.index'))
        ->assertOk()
        ->assertSeeInOrder(['Error Log', 'Force Login', 'Jurnal']);

    $this->actingAs($superAdmin)->get(route('dev.errors.index', ['tab' => 'journal', 'year' => 2026]))
        ->assertOk()
        ->assertSee('Oktober 2026')
        ->assertSee('dev-journal-entry', false)
        ->assertSee('aria-label="Tahun jurnal"', false)
        ->assertSee('<option value="2026" selected>2026</option>', false)
        ->assertSeeInOrder(['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']);
});

test('the journal tab is hidden behind the same 404 gate as the rest of Sentry', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dev.errors.index', ['tab' => 'journal']))
        ->assertNotFound();
});
