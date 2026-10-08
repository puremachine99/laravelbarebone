<?php

use App\Support\PersonName;

it('keeps at most two words of a name', function () {
    expect(PersonName::short('Budi Santoso Wijaya'))->toBe('Budi Santoso')
        ->and(PersonName::short('  Budi   Santoso '))->toBe('Budi Santoso')
        ->and(PersonName::short('Budi'))->toBe('Budi')
        ->and(PersonName::short(null))->toBe('');
});
