<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Employee;
use App\Models\OAuthClient;
use App\Models\Position;
use App\Models\User;
use App\Services\Sso\SsoUserProfile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Example responses for the "Playground SSO" tab of /tech/documentation. The page is public, so the user is made up
 * (never saved), but every response is built by the real SsoUserProfile and paginator: the shape always matches the API.
 */
class SsoPlaygroundSamples
{
    /**
     * Optional scopes, in the order they are combined into a sample key ("profile" is always granted).
     *
     * @var array<int, string>
     */
    public const OPTIONAL_SCOPES = ['email', 'department', 'roles'];

    /**
     * @return array{me: array<string, string>, members: array<string, string>, denied: string}
     */
    public static function all(): array
    {
        $user = self::sampleUser();
        $profile = app(SsoUserProfile::class);
        $me = [];
        $members = [];

        foreach (self::scopeCombinations() as $combination) {
            $key = implode(',', $combination);
            $data = $profile->toArray($user, ['profile', ...$combination]);

            $me[$key] = self::json($data);
            $members[$key] = self::json((new LengthAwarePaginator([$data], 1, 100, 1, ['path' => url('/api/sso/members')]))->toArray());
        }

        return [
            'me' => $me,
            'members' => $members,
            'denied' => self::json(['message' => 'Akun ini tidak memiliki akses ke aplikasi ini.']),
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function scopeCombinations(): array
    {
        $combinations = [[]];

        foreach (self::OPTIONAL_SCOPES as $scope) {
            foreach ($combinations as $combination) {
                $combinations[] = [...$combination, $scope];
            }
        }

        return $combinations;
    }

    private static function sampleUser(): User
    {
        $user = (new User)->forceFill([
            'id' => 42,
            'name' => 'Budi Santoso',
            'username' => 'budi',
            'email' => 'budi@contoh.co.id',
            'email_verified_at' => now(),
        ]);

        $employee = new Employee;
        $employee->setRelation('positionStruktural', null);
        $employee->setRelation('positionFungsional', (new Position)->forceFill(['id' => 12, 'name' => 'Partnership Officer']));
        $employee->setRelation('positionTeknis', null);

        $role = (new Role)->forceFill(['name' => 'Partnership']);
        $role->setRelation('permissions', new Collection([(new Permission)->forceFill(['name' => 'partnership.view'])]));

        $user->setRelation('department', (new Department)->forceFill(['id' => 3, 'name' => 'Departemen Revenue and Growth']));
        $user->setRelation('employee', $employee);
        $user->setRelation('roles', new Collection([$role]));
        $user->setRelation('permissions', new Collection);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Labels of the optional scopes, for the playground checkboxes.
     *
     * @return array<string, string>
     */
    public static function scopeLabels(): array
    {
        return array_intersect_key(OAuthClient::SCOPES, array_flip(self::OPTIONAL_SCOPES));
    }
}
