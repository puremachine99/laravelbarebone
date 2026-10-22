<?php

use App\Models\ApiConsumer;
use App\Support\ResourcePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A module permission plus every per-resource action permission that belongs to it —
 * what a role with full access to that module holds.
 *
 * @return array<int, string>
 */
function moduleAccess(string $modulePermission): array
{
    ResourcePermissions::ensureExist();
    Permission::findOrCreate($modulePermission);

    return [$modulePermission, ...ResourcePermissions::names($modulePermission)];
}

/**
 * A module permission plus only the "view" permissions of its resources — a read-only role.
 *
 * @return array<int, string>
 */
function moduleViewAccess(string $modulePermission): array
{
    return [
        ...array_slice(moduleAccess($modulePermission), 0, 1),
        ...array_filter(ResourcePermissions::names($modulePermission), fn (string $name) => str_ends_with($name, '.view')),
    ];
}

/**
 * Creates an API consumer and returns its plain token for the X-API-Key header of the public lookup API.
 * Server-to-server by default, so requests without an Origin header are accepted.
 *
 * @param  array<string, mixed>  $attributes
 */
function publicApiToken(array $attributes = []): string
{
    $consumer = ApiConsumer::factory()->serverToServer()->create($attributes);
    $token = $consumer->issueToken();
    $consumer->save();

    return $token;
}
