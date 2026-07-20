<?php

use App\Support\Money;

test('normalize strips thousand separators from a masked input', function () {
    expect(Money::normalize('250.000.000'))->toBe(250_000_000.0)
        ->and(Money::normalize('3.500.000.000'))->toBe(3_500_000_000.0)
        ->and(Money::normalize(250_000_000))->toBe(250_000_000.0);
});

test('normalize returns null for empty input', function () {
    expect(Money::normalize(null))->toBeNull()
        ->and(Money::normalize(''))->toBeNull();
});

test('normalize preserves a leading minus sign', function () {
    expect(Money::normalize(-5))->toBe(-5.0)
        ->and(Money::normalize('-5'))->toBe(-5.0);
});

test('abbreviate formats millions and billions in Indonesian short form', function () {
    expect(Money::abbreviate(0))->toBe('Rp 0')
        ->and(Money::abbreviate(500_000_000))->toBe('Rp 500 Jt')
        ->and(Money::abbreviate(3_500_000_000))->toBe('Rp 3,5 M')
        ->and(Money::abbreviate(1_200_000_000))->toBe('Rp 1,2 M');
});

test('exact formats the full, non-abbreviated Rupiah value', function () {
    expect(Money::exact(3_500_000_000))->toBe('Rp 3.500.000.000')
        ->and(Money::exact(0))->toBe('Rp 0');
});
