<?php

use App\Models\AiDataQueryLog;
use App\Models\User;
use App\Support\ResourcePermissions;
use Spatie\Permission\Models\Permission;

function commandUser(array $permissions): User
{
    ResourcePermissions::ensureExist();

    $user = User::factory()->create(['email' => 'tester@example.com']);
    $user->givePermissionTo(array_map(fn (string $name) => Permission::findOrCreate($name), $permissions));

    return $user;
}

test('shows only the datasets the user may use', function () {
    commandUser(['manage-ai-center', 'ai-data-fo-achievement.view']);

    $this->artisan('ai-data:query', ['user' => 'tester@example.com', '--catalog' => true])
        ->expectsOutputToContain('fo_achievement')
        ->doesntExpectOutputToContain('ai_usage')
        ->assertSuccessful();
});

test('runs a request as the user and writes the audit log', function () {
    $user = commandUser(['manage-ai-center', 'ai-data-fo-achievement.view']);

    $this->artisan('ai-data:query', ['user' => 'tester@example.com', 'request' => '{"dataset":"fo_achievement","group_by":["employee"],"all_time":true,"sort":"nilai_closing"}'])
        ->expectsOutputToContain('"dataset": "fo_achievement"')
        ->assertSuccessful();

    expect(AiDataQueryLog::where('user_id', $user->id)->value('outcome'))->toBe('ok');
});

test('reports a denied request', function () {
    commandUser(['manage-ai-center']);

    $this->artisan('ai-data:query', ['user' => 'tester@example.com', 'request' => '{"dataset":"fo_achievement"}'])
        ->expectsOutputToContain('Ditolak')
        ->assertFailed();
});

test('reports an invalid request', function () {
    commandUser(['manage-ai-center', 'ai-data-fo-achievement.view']);

    $this->artisan('ai-data:query', ['user' => 'tester@example.com', 'request' => '{"dataset":"fo_achievement","group_by":["nik"]}'])
        ->expectsOutputToContain('group_by.0')
        ->assertFailed();
});

test('reports an unknown user and invalid JSON', function () {
    $this->artisan('ai-data:query', ['user' => 'nobody@example.com', 'request' => '{}'])->assertFailed();

    commandUser([]);
    $this->artisan('ai-data:query', ['user' => 'tester@example.com', 'request' => 'not json'])
        ->expectsOutputToContain('JSON')
        ->assertFailed();
});
