<?php

use App\Models\InvestorDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeDocsUser(string $roleName): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-investors'));
    $user->assignRole(Role::findOrCreate($roleName));

    return $user;
}

test('guests are redirected to login', function () {
    $this->get(route('investors.docs'))
        ->assertRedirect(route('login'));
});

test('anyone with access to the funding module can view master document', function (string $role) {
    $user = makeDocsUser($role);

    $this->actingAs($user)
        ->get(route('investors.docs'))
        ->assertOk()
        ->assertSee('Master Document');
})->with(['Investor Admin', 'Super Admin', 'Direksi', 'Funding Officer']);

test('a user without access-investors permission cannot access master document', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('investors.docs'))
        ->assertForbidden();
});

test('funding officer and direksi can view documents but not the upload/edit/delete controls', function (string $role) {
    $viewer = makeDocsUser($role);
    $this->actingAs($viewer);
    InvestorDocument::factory()->create(['title' => 'Brosur Produk A']);

    Volt::test('investors.docs-index')
        ->assertOk()
        ->assertSee('Brosur Produk A')
        ->assertDontSee('Upload Document')
        ->assertDontSee('View Deleted');
})->with(['Funding Officer', 'Direksi']);

test('investor admin can upload a document to s3, and its file name follows the title input', function () {
    Storage::fake('s3');

    $admin = makeDocsUser('Investor Admin');
    $this->actingAs($admin);

    $file = UploadedFile::fake()->create('original-name.pdf', 500, 'application/pdf');

    Volt::test('investors.docs-index')
        ->set('title', 'Brosur Produk Reksa Dana A')
        ->set('description', 'Materi pitching produk reksa dana A')
        ->set('documentFile', $file)
        ->call('saveDocument')
        ->assertHasNoErrors();

    $document = InvestorDocument::first();

    expect($document->title)->toBe('Brosur Produk Reksa Dana A')
        ->and($document->file_name)->toBe('Brosur Produk Reksa Dana A.pdf')
        ->and($document->original_file_name)->toBe('original-name.pdf')
        ->and($document->extension)->toBe('pdf')
        ->and($document->uploaded_by)->toBe($admin->id)
        ->and($document->file_path)->toStartWith('investors/investor-docs/testing/')
        ->and($document->preview_path)->toBeNull();

    Storage::disk('s3')->assertExists($document->file_path);
});

test('uploading an image generates a compressed thumbnail preview stored on s3', function () {
    Storage::fake('s3');

    $admin = makeDocsUser('Investor Admin');
    $this->actingAs($admin);

    Volt::test('investors.docs-index')
        ->set('title', 'Foto Kantor')
        ->set('documentFile', UploadedFile::fake()->image('photo.jpg', 1600, 1200))
        ->call('saveDocument')
        ->assertHasNoErrors();

    $document = InvestorDocument::first();

    expect($document->preview_path)->not->toBeNull()
        ->and($document->preview_path)->toStartWith('investors/investor-docs/testing/previews/')
        ->and($document->preview_path)->toEndWith('.webp');

    Storage::disk('s3')->assertExists($document->file_path);
    Storage::disk('s3')->assertExists($document->preview_path);
});

test('a document with a thumbnail preview renders it as an image on the card', function () {
    $admin = makeDocsUser('Investor Admin');
    $this->actingAs($admin);

    InvestorDocument::factory()->create([
        'title' => 'Company Profile',
        'preview_path' => 'investors/investor-docs/testing/previews/fake.webp',
    ]);

    Volt::test('investors.docs-index')
        ->assertOk()
        ->assertSee('Company Profile')
        ->assertSeeHtml('<img');
});

test('replacing the file on an image document deletes the old preview from s3 too', function () {
    Storage::fake('s3');

    $admin = makeDocsUser('Investor Admin');
    $this->actingAs($admin);

    $document = InvestorDocument::factory()->create([
        'file_path' => 'investors/investor-docs/testing/old.jpg',
        'preview_path' => 'investors/investor-docs/testing/previews/old.webp',
        'extension' => 'jpg',
    ]);
    Storage::disk('s3')->put($document->file_path, 'old-content');
    Storage::disk('s3')->put($document->preview_path, 'old-preview');

    Volt::test('investors.docs-index')
        ->call('editDocument', $document->id)
        ->set('documentFile', UploadedFile::fake()->image('new.jpg', 800, 600))
        ->call('saveDocument')
        ->assertHasNoErrors();

    Storage::disk('s3')->assertMissing('investors/investor-docs/testing/old.jpg');
    Storage::disk('s3')->assertMissing('investors/investor-docs/testing/previews/old.webp');
});

test('uploading rejects a disallowed file type', function () {
    Storage::fake('s3');

    $admin = makeDocsUser('Investor Admin');
    $this->actingAs($admin);

    $file = UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload');

    Volt::test('investors.docs-index')
        ->set('title', 'Bad File')
        ->set('documentFile', $file)
        ->call('saveDocument')
        ->assertHasErrors(['documentFile']);

    expect(InvestorDocument::count())->toBe(0);
});

test('uploading rejects a file larger than 1MB', function () {
    Storage::fake('s3');

    $admin = makeDocsUser('Investor Admin');
    $this->actingAs($admin);

    $file = UploadedFile::fake()->create('too-big.pdf', 1025, 'application/pdf');

    Volt::test('investors.docs-index')
        ->set('title', 'Too Big')
        ->set('documentFile', $file)
        ->call('saveDocument')
        ->assertHasErrors(['documentFile']);

    expect(InvestorDocument::count())->toBe(0);
});

test('spreadsheet and image files are accepted for upload', function (string $filename, string $mime) {
    Storage::fake('s3');

    $admin = makeDocsUser('Investor Admin');
    $this->actingAs($admin);

    Volt::test('investors.docs-index')
        ->set('title', 'Some Document')
        ->set('documentFile', UploadedFile::fake()->create($filename, 100, $mime))
        ->call('saveDocument')
        ->assertHasNoErrors();

    expect(InvestorDocument::count())->toBe(1);
})->with([
    ['data.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['data.xls', 'application/vnd.ms-excel'],
    ['photo.jpg', 'image/jpeg'],
    ['photo.png', 'image/png'],
]);

test('the extension badge color matches the file type', function (string $extension, string $expectedClass) {
    $document = InvestorDocument::factory()->make(['extension' => $extension]);

    expect($document->badgeClasses())->toBe($expectedClass);
})->with([
    ['pdf', 'badge-error'],
    ['docx', 'badge-info'],
    ['doc', 'badge-info'],
    ['xlsx', 'badge-success'],
    ['xls', 'badge-success'],
    ['pptx', 'badge-warning'],
    ['ppt', 'badge-warning'],
    ['jpg', 'bg-pink-500/15 text-pink-600 border-pink-500/20'],
    ['png', 'bg-pink-500/15 text-pink-600 border-pink-500/20'],
    ['zip', 'badge-neutral'],
]);

test('funding officer and direksi cannot call the mutating actions directly even though they can view the page', function (string $role) {
    Storage::fake('s3');

    $viewer = makeDocsUser($role);
    $this->actingAs($viewer);

    Volt::test('investors.docs-index')
        ->set('title', 'Hack')
        ->set('documentFile', UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'))
        ->call('saveDocument')
        ->assertStatus(403);

    expect(InvestorDocument::count())->toBe(0);
})->with(['Funding Officer', 'Direksi']);

test('editing a document without replacing the file keeps the old file but updates the file name to match the new title', function () {
    Storage::fake('s3');

    $admin = makeDocsUser('Investor Admin');
    $this->actingAs($admin);

    $document = InvestorDocument::factory()->create([
        'title' => 'Old Title',
        'file_name' => 'Old Title.pdf',
        'file_path' => 'investors/investor-docs/testing/keep-me.pdf',
        'extension' => 'pdf',
    ]);
    Storage::disk('s3')->put($document->file_path, 'fake-content');

    Volt::test('investors.docs-index')
        ->call('editDocument', $document->id)
        ->set('title', 'New Title')
        ->set('description', 'Updated description')
        ->call('saveDocument')
        ->assertHasNoErrors();

    $document->refresh();

    expect($document->title)->toBe('New Title')
        ->and($document->file_name)->toBe('New Title.pdf')
        ->and($document->file_path)->toBe('investors/investor-docs/testing/keep-me.pdf');

    Storage::disk('s3')->assertExists($document->file_path);
});

test('editing a document with a new file replaces and deletes the old file from s3', function () {
    Storage::fake('s3');

    $admin = makeDocsUser('Investor Admin');
    $this->actingAs($admin);

    $document = InvestorDocument::factory()->create([
        'file_path' => 'investors/investor-docs/testing/old-file.pdf',
        'extension' => 'pdf',
    ]);
    Storage::disk('s3')->put($document->file_path, 'old-content');

    $newFile = UploadedFile::fake()->create('replacement.pdf', 300, 'application/pdf');

    Volt::test('investors.docs-index')
        ->call('editDocument', $document->id)
        ->set('documentFile', $newFile)
        ->call('saveDocument')
        ->assertHasNoErrors();

    $document->refresh();

    Storage::disk('s3')->assertMissing('investors/investor-docs/testing/old-file.pdf');
    Storage::disk('s3')->assertExists($document->file_path);
    expect($document->file_path)->not->toBe('investors/investor-docs/testing/old-file.pdf');
});

test('deleting a document soft deletes it, hides it from the active list, and it can be restored', function () {
    $admin = makeDocsUser('Investor Admin');
    $this->actingAs($admin);

    $document = InvestorDocument::factory()->create(['title' => 'To Be Deleted']);

    $component = Volt::test('investors.docs-index')
        ->call('confirmDelete', $document->id)
        ->call('deleteDocument');

    expect($document->fresh()->trashed())->toBeTrue();
    $component->assertDontSee('To Be Deleted');

    $component->call('toggleTrashed')->assertSee('To Be Deleted');

    $component->call('restoreDocument', $document->id);

    expect($document->fresh()->trashed())->toBeFalse();
});

test('search filters documents by title', function () {
    $admin = makeDocsUser('Investor Admin');
    $this->actingAs($admin);

    InvestorDocument::factory()->create(['title' => 'Brosur Reksa Dana']);
    InvestorDocument::factory()->create(['title' => 'Company Profile']);

    Volt::test('investors.docs-index')
        ->set('search', 'reksa')
        ->assertSee('Brosur Reksa Dana')
        ->assertDontSee('Company Profile');
});

test('documents are sorted by newest upload first', function () {
    $admin = makeDocsUser('Investor Admin');
    $this->actingAs($admin);

    $older = InvestorDocument::factory()->create(['title' => 'Older Doc', 'created_at' => now()->subDays(2)]);
    $newer = InvestorDocument::factory()->create(['title' => 'Newer Doc', 'created_at' => now()]);

    $documents = Volt::test('investors.docs-index')->instance()->documents();

    expect($documents->first()->id)->toBe($newer->id)
        ->and($documents->last()->id)->toBe($older->id);
});

test('pdf and image documents are previewable, everything else is not', function (string $extension, bool $expected) {
    $document = InvestorDocument::factory()->make(['extension' => $extension]);

    expect($document->isPreviewable())->toBe($expected);
})->with([
    ['pdf', true],
    ['jpg', true],
    ['png', true],
    ['docx', false],
    ['doc', false],
    ['xlsx', false],
    ['pptx', false],
]);

test('opening the preview modal on a previewable document sets it as the active preview', function () {
    $admin = makeDocsUser('Investor Admin');
    $this->actingAs($admin);

    $document = InvestorDocument::factory()->create(['title' => 'Brosur PDF', 'extension' => 'pdf']);

    $component = Volt::test('investors.docs-index')
        ->call('openPreview', $document->id);

    expect($component->get('showPreviewModal'))->toBeTrue()
        ->and($component->get('previewDocumentId'))->toBe($document->id);
});

test('opening the preview modal on a non-previewable document does nothing', function () {
    $admin = makeDocsUser('Investor Admin');
    $this->actingAs($admin);

    $document = InvestorDocument::factory()->create(['title' => 'Company Deck', 'extension' => 'pptx']);

    $component = Volt::test('investors.docs-index')
        ->call('openPreview', $document->id);

    expect($component->get('showPreviewModal'))->toBeFalse()
        ->and($component->get('previewDocumentId'))->toBeNull();
});

test('the preview button is disabled for non-previewable document types', function () {
    $admin = makeDocsUser('Investor Admin');
    $this->actingAs($admin);

    InvestorDocument::factory()->create(['title' => 'Company Deck', 'extension' => 'pptx']);

    Volt::test('investors.docs-index')
        ->assertOk()
        ->assertSeeHtml('disabled');
});

test('funding officer and direksi have no edit/delete overlay menu but can still open the preview', function (string $role) {
    $viewer = makeDocsUser($role);
    $this->actingAs($viewer);

    $document = InvestorDocument::factory()->create(['title' => 'Brosur PDF', 'extension' => 'pdf']);

    Volt::test('investors.docs-index')
        ->assertOk()
        ->assertDontSeeHtml('wire:click="editDocument('.$document->id.')"')
        ->assertDontSeeHtml('wire:click="confirmDelete('.$document->id.')"')
        ->call('openPreview', $document->id)
        ->assertSet('showPreviewModal', true);
})->with(['Funding Officer', 'Direksi']);
