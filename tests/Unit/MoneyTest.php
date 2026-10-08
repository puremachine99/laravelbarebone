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

test('normalize drops a stray decimal suffix instead of folding it into the integer', function () {
    // A masked input can end up with a leftover ",00" (e.g. stale mask state reused across
    // records). Blindly stripping every non-digit used to turn "500.000.000,00" into the
    // digit string "50000000000" — Rp 500 juta saved as Rp 50 M.
    expect(Money::normalize('500.000.000,00'))->toBe(500_000_000.0)
        ->and(Money::normalize('3.500.000.000,50'))->toBe(3_500_000_000.0);
});

test('abbreviate formats millions and billions in Indonesian short form', function () {
    expect(Money::abbreviate(0))->toBe('Rp 0')
        ->and(Money::abbreviate(500_000_000))->toBe('Rp 500,00 Jt')
        ->and(Money::abbreviate(3_500_000_000))->toBe('Rp 3,50 M')
        ->and(Money::abbreviate(1_200_000_000))->toBe('Rp 1,20 M');
});

test('exact formats the full, non-abbreviated Rupiah value', function () {
    expect(Money::exact(3_500_000_000))->toBe('Rp 3.500.000.000')
        ->and(Money::exact(0))->toBe('Rp 0');
});

it('truncates abbreviated amounts to two decimals instead of rounding', function () {
    expect(Money::abbreviate(3_579_999_999))->toBe('Rp 3,57 M')
        ->and(Money::abbreviate(1_999_999_999))->toBe('Rp 1,99 M')
        ->and(Money::abbreviate(12_345_678_000))->toBe('Rp 12,34 M')
        ->and(Money::abbreviate(2_999_999))->toBe('Rp 2,99 Jt')
        ->and(Money::abbreviate(1_234_567))->toBe('Rp 1,23 Jt');
});
