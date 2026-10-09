<?php

use App\Enums\ChatPresenceStatus;
use App\Events\Chat\UserStatusChanged;
use App\Livewire\Chat\ChatIndex;
use App\Livewire\Chat\UnreadBadge;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function chatUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('manage-chat'));

    return $user;
}

test('guests are redirected to login', function () {
    $this->get(route('chat'))->assertRedirect(route('login'));
});

test('starting a new chat creates a single direct conversation between the two users', function () {
    $me = chatUser();
    $other = User::factory()->create();

    Livewire::actingAs($me)
        ->test(ChatIndex::class)
        ->call('startNewChat', $other->id)
        ->assertSet('selectedConversationId', fn ($id) => $id !== null);

    expect(Conversation::count())->toBe(1);

    $conversation = Conversation::first();
    expect($conversation->participants->pluck('id')->sort()->values()->all())
        ->toBe(collect([$me->id, $other->id])->sort()->values()->all());

    // Starting a new chat with the same person again reuses the existing conversation.
    Livewire::actingAs($me)
        ->test(ChatIndex::class)
        ->call('startNewChat', $other->id);

    expect(Conversation::count())->toBe(1);
});

test('sending a message persists it and bumps the conversation to the top', function () {
    $me = chatUser();
    $other = User::factory()->create();
    $conversation = Conversation::findOrCreateDirect($me, $other);

    Livewire::actingAs($me)
        ->test(ChatIndex::class)
        ->call('selectConversation', $conversation->id)
        ->set('messageInput', 'Halo, ada waktu untuk diskusi?')
        ->call('sendMessage')
        ->assertHasNoErrors();

    $message = Message::first();

    expect($message)->not->toBeNull()
        ->and($message->conversation_id)->toBe($conversation->id)
        ->and($message->user_id)->toBe($me->id)
        ->and($message->body)->toBe('Halo, ada waktu untuk diskusi?');

    expect($conversation->fresh()->last_message_at)->not->toBeNull();
});

test('sending an empty message without an attachment is rejected', function () {
    $me = chatUser();
    $other = User::factory()->create();
    $conversation = Conversation::findOrCreateDirect($me, $other);

    Livewire::actingAs($me)
        ->test(ChatIndex::class)
        ->call('selectConversation', $conversation->id)
        ->set('messageInput', '   ')
        ->call('sendMessage')
        ->assertHasErrors('messageInput');

    expect(Message::count())->toBe(0);
});

test('sending an image attachment stores the original and a compressed webp preview', function () {
    Storage::fake('s3');

    $me = chatUser();
    $other = User::factory()->create();
    $conversation = Conversation::findOrCreateDirect($me, $other);

    $image = UploadedFile::fake()->image('photo.jpg', 1600, 1200);

    Livewire::actingAs($me)
        ->test(ChatIndex::class)
        ->call('selectConversation', $conversation->id)
        ->set('attachment', $image)
        ->call('sendMessage')
        ->assertHasNoErrors();

    $message = Message::first();

    expect($message->attachment_path)->not->toBeNull()
        ->and($message->attachment_preview_path)->not->toBeNull()
        ->and($message->attachment_preview_path)->toEndWith('.webp')
        ->and($message->attachment_preview_path)->not->toBe($message->attachment_path)
        ->and($message->isImage())->toBeTrue();

    Storage::disk('s3')->assertExists($message->attachment_path);
    Storage::disk('s3')->assertExists($message->attachment_preview_path);

    expect($message->attachmentUrl())->not->toBe($message->previewUrl());
});

test('a user who is not a participant cannot open or post to a conversation', function () {
    $userA = chatUser();
    $userB = User::factory()->create();
    $intruder = chatUser();

    $conversation = Conversation::findOrCreateDirect($userA, $userB);

    Livewire::actingAs($intruder)
        ->test(ChatIndex::class)
        ->call('selectConversation', $conversation->id)
        ->assertStatus(403);
});

test('a user can change their own presence status and it broadcasts to others', function () {
    Event::fake([UserStatusChanged::class]);

    $me = chatUser();

    Livewire::actingAs($me)
        ->test(ChatIndex::class)
        ->call('updateMyStatus', 'busy');

    expect($me->fresh()->presence_status)->toBe(ChatPresenceStatus::Busy);

    Event::assertDispatched(UserStatusChanged::class, fn (UserStatusChanged $event) => $event->user->is($me));
});

test('sidebar unread badge counts only unread messages from others', function () {
    $me = chatUser();
    $other = User::factory()->create();
    $conversation = Conversation::findOrCreateDirect($me, $other);

    Message::factory()->count(2)->create(['conversation_id' => $conversation->id, 'user_id' => $other->id]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $me->id]);

    Livewire::actingAs($me)
        ->test(UnreadBadge::class)
        ->assertSeeText('2');

    $conversation->participants()->updateExistingPivot($me->id, ['last_read_at' => now()->addMinute()]);

    Livewire::actingAs($me)
        ->test(UnreadBadge::class)
        ->assertDontSeeHtml('rounded-full');
});
