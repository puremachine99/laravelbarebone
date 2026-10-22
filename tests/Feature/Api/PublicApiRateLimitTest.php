<?php

use App\Models\ApiConsumer;
use Illuminate\Support\Facades\DB;

test('the public lookup API rejects requests without an API key', function () {
    $this->getJson('/api/provinces')
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate')
        ->assertJsonPath('message', 'API key wajib dikirim di header X-API-Key.');
});

test('the public lookup API rejects an unknown or revoked API key', function () {
    $this->getJson('/api/provinces', ['X-API-Key' => 'smid_tidak_ada'])->assertUnauthorized();
    $this->getJson('/api/provinces', ['X-API-Key' => 'bukan-format-token'])->assertUnauthorized();

    $consumer = ApiConsumer::factory()->serverToServer()->create();
    $token = $consumer->issueToken();
    $consumer->save();

    $this->getJson('/api/provinces', ['X-API-Key' => $token])->assertOk();

    $consumer->update(['revoked_at' => now()]);

    $this->getJson('/api/provinces', ['X-API-Key' => $token])->assertUnauthorized();
});

test('a browser call must come from one of the registered websites', function () {
    $token = publicApiToken(['allowed_origins' => ['portal.contoh.go.id', '*.mitra.id'], 'allow_server_requests' => false]);

    $this->getJson('/api/provinces', ['X-API-Key' => $token, 'Origin' => 'https://portal.contoh.go.id'])->assertOk();
    $this->getJson('/api/provinces', ['X-API-Key' => $token, 'Origin' => 'https://app.mitra.id'])->assertOk();
    $this->getJson('/api/provinces', ['X-API-Key' => $token, 'Referer' => 'https://portal.contoh.go.id/form?x=1'])->assertOk();

    $this->getJson('/api/provinces', ['X-API-Key' => $token, 'Origin' => 'https://evil.example.com'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Website evil.example.com tidak terdaftar untuk API key ini.');
    $this->getJson('/api/provinces', ['X-API-Key' => $token, 'Origin' => 'https://mitra.id.evil.com'])->assertForbidden();
});

test('server requests without Origin or Referer need the server permission', function () {
    $browserOnly = publicApiToken(['allow_server_requests' => false]);
    $server = publicApiToken(['allow_server_requests' => true]);

    $this->getJson('/api/provinces', ['X-API-Key' => $browserOnly])->assertForbidden();
    $this->getJson('/api/provinces', ['X-API-Key' => $server])->assertOk();
});

test('this site\'s own documentation playground is accepted with any valid key', function () {
    config(['app.url' => 'https://nakama.smartid.test']);
    $token = publicApiToken(['allowed_origins' => ['portal.contoh.go.id'], 'allow_server_requests' => false]);

    $this->getJson('/api/provinces', ['X-API-Key' => $token, 'Referer' => 'https://nakama.smartid.test/tech/documentation'])->assertOk();
});

test('the token lookup is cached, so a repeated request does not query the consumer table', function () {
    $token = publicApiToken();
    $this->getJson('/api/provinces', ['X-API-Key' => $token])->assertOk();

    DB::enableQueryLog();
    $this->getJson('/api/provinces', ['X-API-Key' => $token])->assertOk();

    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => str_contains($sql, 'from "api_consumers"')))->toBeEmpty();
});

test('rotating the token invalidates the cached old token immediately', function () {
    $consumer = ApiConsumer::factory()->serverToServer()->create();
    $old = $consumer->issueToken();
    $consumer->save();
    $this->getJson('/api/provinces', ['X-API-Key' => $old])->assertOk();

    $new = $consumer->issueToken();
    $consumer->save();

    $this->getJson('/api/provinces', ['X-API-Key' => $old])->assertUnauthorized();
    $this->getJson('/api/provinces', ['X-API-Key' => $new])->assertOk();
});

test('the rate limit is counted per API key with its own limit', function () {
    $small = publicApiToken(['rate_limit_per_minute' => 3]);
    $other = publicApiToken();

    foreach (range(1, 3) as $request) {
        $this->getJson('/api/provinces', ['X-API-Key' => $small])->assertOk();
    }

    $this->getJson('/api/opds/search', ['X-API-Key' => $small])->assertTooManyRequests();
    $this->getJson('/api/provinces', ['X-API-Key' => $other])
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', 120)
        ->assertHeader('X-RateLimit-Remaining', 119);
});

test('usage is recorded after the response, at most once per interval', function () {
    $consumer = ApiConsumer::factory()->create(['allowed_origins' => ['portal.contoh.go.id']]);
    $token = $consumer->issueToken();
    $consumer->save();

    $this->getJson('/api/provinces', ['X-API-Key' => $token, 'Origin' => 'https://portal.contoh.go.id'])->assertOk();

    expect($consumer->fresh()->last_used_at)->not->toBeNull()
        ->and($consumer->fresh()->last_used_origin)->toBe('portal.contoh.go.id');
});

test('this site\'s own pages are accepted even when opened under another host than APP_URL', function () {
    config(['app.url' => 'http://localhost:8000']);
    $token = publicApiToken(['allowed_origins' => ['portal.contoh.go.id'], 'allow_server_requests' => false]);

    $this->getJson('http://smartdashboard.test/api/provinces', ['X-API-Key' => $token, 'Referer' => 'http://smartdashboard.test/tech/documentation'])->assertOk();
    $this->getJson('http://smartdashboard.test/api/provinces', ['X-API-Key' => $token, 'Origin' => 'http://other.test'])->assertForbidden();
});
