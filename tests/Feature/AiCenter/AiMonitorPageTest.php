<?php

use App\Livewire\Tech\AiMonitor;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiUsageLog;
use App\Models\AiUsageSummary;
use App\Models\User;
use App\Support\ResourcePermissions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function aiMonitorUser(): User
{
    ResourcePermissions::ensureExist();

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-technology'), Permission::findOrCreate('tech-ai-monitor.view'));

    return $user;
}

test('guests are redirected to login', function () {
    $this->get(route('tech.ai-monitor'))->assertRedirect(route('login'));
});

test('users without the AI Monitor permission are forbidden', function () {
    ResourcePermissions::ensureExist();

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('access-technology'), Permission::findOrCreate('manage-ai-center'));

    $this->actingAs($user)->get(route('tech.ai-monitor'))->assertForbidden();
});

test('the page shows the usage log and totals but never the chat text', function () {
    $this->travelTo('2026-10-13 10:00:00');

    $message = AiMessage::factory()->for(AiConversation::factory(), 'conversation')->create(['content' => 'RAHASIA gaji direksi']);
    AiUsageLog::factory()->create(['user_name' => 'Budi Santoso', 'department' => 'Finance', 'prompt_tokens' => 1200, 'output_tokens' => 300, 'total_tokens' => 1500]);
    AiUsageLog::factory()->failed('upstream_timeout')->create(['user_name' => 'Sari']);

    $this->actingAs(aiMonitorUser())
        ->get(route('tech.ai-monitor'))
        ->assertOk()
        ->assertSee('AI Monitor')
        ->assertSee('Budi Santoso')
        ->assertSee('in=1.200')
        ->assertSee('total=')
        ->assertSee('upstream_timeout')
        ->assertDontSee($message->content);
});

test('the log can be filtered by outcome and user', function () {
    AiUsageLog::factory()->create(['user_name' => 'Budi Santoso']);
    AiUsageLog::factory()->failed()->create(['user_name' => 'Sari Wulandari']);

    $this->actingAs(aiMonitorUser());

    Livewire::test(AiMonitor::class)
        ->assertSee('Budi Santoso')
        ->assertSee('Sari Wulandari')
        ->set('outcome', 'error')
        ->assertDontSee('Budi Santoso')
        ->assertSee('Sari Wulandari')
        ->call('resetFilters')
        ->set('search', 'budi')
        ->assertSee('Budi Santoso')
        ->assertDontSee('Sari Wulandari');
});

test('the summary tab lists weekly and monthly summaries', function () {
    AiUsageSummary::create(['period' => 'weekly', 'period_start' => '2026-10-05', 'period_end' => '2026-10-11', 'total_tokens' => 123456, 'breakdown' => ['by_department' => [['name' => 'Finance', 'requests' => 3, 'total_tokens' => 123456, 'cost_usd' => null]]]]);
    AiUsageSummary::create(['period' => 'monthly', 'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'total_tokens' => 987654, 'breakdown' => []]);

    $this->actingAs(aiMonitorUser());

    Livewire::test(AiMonitor::class)
        ->set('tab', 'summary')
        ->assertSee('123.456')
        ->assertSee('Finance: 123.456')
        ->assertDontSee('987.654')
        ->set('period', 'monthly')
        ->assertSee('987.654');
});

test('the log shows the types of the files the AI generated', function () {
    AiUsageLog::factory()->create(['user_name' => 'Budi Santoso', 'generated_file_types' => ['xlsx', 'pptx']]);
    AiUsageLog::factory()->create(['user_name' => 'Sari Wulandari']);

    $this->actingAs(aiMonitorUser());

    Livewire::test(AiMonitor::class)
        ->assertSeeHtml('files=<span class="text-code-ok">xlsx,pptx</span>')
        ->assertSee('Sari Wulandari');
});
