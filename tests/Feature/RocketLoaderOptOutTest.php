<?php

/**
 * Cloudflare Rocket Loader menulis ulang & menunda eksekusi <script> yang tidak
 * ditandai `data-cfasync="false"`, yang merusak urutan boot Livewire/Alpine.
 * Test ini menjaga agar tag script bawaan framework selalu ikut opt-out.
 */
function scriptTags(string $html): array
{
    preg_match_all('/<script\b[^>]*\bsrc="[^"]*"[^>]*>/i', $html, $matches);

    return $matches[0];
}

it('opts the vite script tag out of rocket loader', function () {
    $tags = scriptTags($this->get(route('login'))->getContent());

    $viteTags = array_values(array_filter($tags, fn (string $tag) => str_contains($tag, '/build/assets/')));

    expect($viteTags)->not->toBeEmpty();

    foreach ($viteTags as $tag) {
        expect($tag)->toContain('data-cfasync="false"');
    }
});

it('opts the livewire script tag out of rocket loader', function () {
    $tags = scriptTags($this->get(route('login'))->getContent());

    $livewireTags = array_values(array_filter($tags, fn (string $tag) => str_contains($tag, 'livewire')));

    expect($livewireTags)->not->toBeEmpty();

    foreach ($livewireTags as $tag) {
        expect($tag)->toContain('data-cfasync="false"');
    }
});
