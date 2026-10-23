<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('profile page is rendered for authenticated users', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('profile.show'));

    $response->assertOk();
});

test('guests are redirected to login', function () {
    $response = $this->get(route('profile.show'));

    $response->assertRedirect(route('login'));
});
