<?php

use Illuminate\Support\Facades\Blade;

it('renders the short name with the full name as tooltip', function () {
    $html = Blade::render('<x-short-name name="Budi Santoso Wijaya" />');

    expect($html)->toContain('Budi Santoso')->toContain('data-tip="Budi Santoso Wijaya"')
        ->and(Blade::render('<x-short-name name="Budi Santoso" />'))->not->toContain('data-tip')
        ->and(trim(Blade::render('<x-short-name :name="null" />')))->toBe('-');
});

it('shows full rupiah by default and the abbreviated form only when requested', function () {
    expect(trim(Blade::render('<x-currency-abbr :value="3579999999" />')))->toContain('Rp 3.579.999.999')->not->toContain(' M')
        ->and(Blade::render('<x-currency-abbr abbreviated :value="3579999999" />'))->toContain('Rp 3,57 M');
});
