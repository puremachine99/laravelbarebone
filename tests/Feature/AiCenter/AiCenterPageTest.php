<?php

use App\Enums\AiErrorCode;
use App\Livewire\Ai\AiCenter;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function aiCenterUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('manage-ai-center'));

    return $user;
}

test('guests are redirected to login', function () {
    $this->get(route('ai-center'))->assertRedirect(route('login'));
});

test('users without the AI Center permission are forbidden', function () {
    $this->actingAs(User::factory()->create())->get(route('ai-center'))->assertForbidden();
});

test('the chat page shows the MVP chat box instead of the upcoming-feature teaser', function () {
    $this->actingAs(aiCenterUser())
        ->get(route('ai-center'))
        ->assertOk()
        ->assertSee('Jawaban AI bisa keliru')
        ->assertSee('Ringkas teks')
        ->assertSee('0/50 pesan')
        ->assertDontSee('Upcoming Feature')
        ->assertDontSee('Update proyek Pemkab Malang');
});

test('the list only shows my own conversations', function () {
    $me = aiCenterUser();
    AiConversation::factory()->for($me)->create(['title' => 'Draf email vendor']);
    AiConversation::factory()->create(['title' => 'Rahasia orang lain']);

    Livewire::actingAs($me)
        ->test(AiCenter::class)
        ->assertSee('Draf email vendor')
        ->assertDontSee('Rahasia orang lain');
});

test('searching filters conversations by title', function () {
    $me = aiCenterUser();
    AiConversation::factory()->for($me)->create(['title' => 'Ringkasan notulen']);
    AiConversation::factory()->for($me)->create(['title' => 'Draf email vendor']);

    Livewire::actingAs($me)
        ->test(AiCenter::class)
        ->set('search', 'notulen')
        ->assertSee('Ringkasan notulen')
        ->assertDontSee('Draf email vendor');
});

test('selecting a conversation shows its messages with the check-before-use label', function () {
    $me = aiCenterUser();
    $conversation = AiConversation::factory()->for($me)->create();
    AiMessage::factory()->for($conversation, 'conversation')->create(['content' => 'Apa itu SPJ?']);
    AiMessage::factory()->assistant()->for($conversation, 'conversation')->create(['content' => 'SPJ adalah **Surat Pertanggungjawaban**.']);

    Livewire::actingAs($me)
        ->test(AiCenter::class)
        ->call('selectConversation', $conversation->id)
        ->assertSet('selectedConversationId', $conversation->id)
        ->assertSee('Apa itu SPJ?')
        ->assertSeeHtml('<strong>Surat Pertanggungjawaban</strong>')
        ->assertSee('Jawaban AI, perlu dicek');
});

test('opening someone else\'s conversation fails, even for Super Admin', function () {
    $superAdmin = aiCenterUser();
    $superAdmin->assignRole(Role::findOrCreate('Super Admin'));
    $othersConversation = AiConversation::factory()->create();

    expect(fn () => Livewire::actingAs($superAdmin)
        ->test(AiCenter::class)
        ->call('selectConversation', $othersConversation->id)
    )->toThrow(ModelNotFoundException::class);
});

test('the AI answer is rendered as sanitized Markdown', function () {
    $me = aiCenterUser();
    $conversation = AiConversation::factory()->for($me)->create();
    AiMessage::factory()->assistant()->for($conversation, 'conversation')->create([
        'content' => "Halo <script>alert('x')</script>\n\n[klik](javascript:alert(1))",
    ]);

    Livewire::actingAs($me)
        ->test(AiCenter::class)
        ->call('selectConversation', $conversation->id)
        ->assertDontSeeHtml("<script>alert('x')</script>")
        ->assertDontSeeHtml('href="javascript:');
});

test('a failed answer shows the friendly error message', function () {
    $me = aiCenterUser();
    $conversation = AiConversation::factory()->for($me)->create();
    AiMessage::factory()->for($conversation, 'conversation')->create();
    AiMessage::factory()->failed(AiErrorCode::UpstreamQuota)->for($conversation, 'conversation')->create();

    Livewire::actingAs($me)
        ->test(AiCenter::class)
        ->call('selectConversation', $conversation->id)
        ->assertSee('Kuota layanan AI sedang habis')
        ->assertSee('Coba lagi');
});

test('I can rename my conversation', function () {
    $me = aiCenterUser();
    $conversation = AiConversation::factory()->for($me)->create(['title' => 'Lama']);

    Livewire::actingAs($me)
        ->test(AiCenter::class)
        ->call('selectConversation', $conversation->id)
        ->call('openRenameModal')
        ->assertSet('renameTitle', 'Lama')
        ->set('renameTitle', '  Judul baru  ')
        ->call('renameConversation')
        ->assertHasNoErrors()
        ->assertSet('showRenameModal', false);

    expect($conversation->fresh()->title)->toBe('Judul baru');
});

test('a blank title is rejected', function () {
    $me = aiCenterUser();
    $conversation = AiConversation::factory()->for($me)->create();

    Livewire::actingAs($me)
        ->test(AiCenter::class)
        ->call('selectConversation', $conversation->id)
        ->set('renameTitle', '')
        ->call('renameConversation')
        ->assertHasErrors(['renameTitle' => 'required']);
});

test('deleting my conversation soft-deletes it and closes the thread', function () {
    $me = aiCenterUser();
    $conversation = AiConversation::factory()->for($me)->create();

    Livewire::actingAs($me)
        ->test(AiCenter::class)
        ->call('selectConversation', $conversation->id)
        ->call('deleteConversation')
        ->assertSet('selectedConversationId', null);

    expect($conversation->fresh()->trashed())->toBeTrue();
});

test('rating an answer toggles the feedback', function () {
    $me = aiCenterUser();
    $conversation = AiConversation::factory()->for($me)->create();
    $answer = AiMessage::factory()->assistant()->for($conversation, 'conversation')->create();

    $component = Livewire::actingAs($me)
        ->test(AiCenter::class)
        ->call('selectConversation', $conversation->id)
        ->call('rateMessage', $answer->id, 1);

    expect($answer->fresh()->feedback)->toBe(1);

    $component->call('rateMessage', $answer->id, -1);
    expect($answer->fresh()->feedback)->toBe(-1);

    $component->call('rateMessage', $answer->id, -1);
    expect($answer->fresh()->feedback)->toBeNull();
});

test('my own messages cannot be rated', function () {
    $me = aiCenterUser();
    $conversation = AiConversation::factory()->for($me)->create();
    $question = AiMessage::factory()->for($conversation, 'conversation')->create();

    Livewire::actingAs($me)
        ->test(AiCenter::class)
        ->call('selectConversation', $conversation->id)
        ->call('rateMessage', $question->id, 1);

    expect($question->fresh()->feedback)->toBeNull();
});

test('the daily quota counter includes messages from deleted conversations', function () {
    $me = aiCenterUser();
    $deleted = AiConversation::factory()->for($me)->create();
    AiMessage::factory()->count(3)->for($deleted, 'conversation')->create();
    AiMessage::factory()->assistant()->for($deleted, 'conversation')->create();
    $deleted->delete();

    Livewire::actingAs($me)
        ->test(AiCenter::class)
        ->assertSee('3/50 pesan');
});
