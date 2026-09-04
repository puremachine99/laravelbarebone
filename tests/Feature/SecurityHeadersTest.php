<?php

test('responses include baseline security headers', function () {
    $response = $this->get(route('login'));

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('HSTS header is only sent over secure requests', function () {
    $response = $this->get(route('login'));

    $response->assertHeaderMissing('Strict-Transport-Security');

    $response = $this->call('GET', route('login'), server: ['HTTPS' => 'on', 'HTTP_X_FORWARDED_PROTO' => 'https']);

    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
