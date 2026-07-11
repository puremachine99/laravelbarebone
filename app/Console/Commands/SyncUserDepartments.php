<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:sync-user-departments')]
#[Description('Backfill users.department_id and module permissions from existing Employee records')]
class SyncUserDepartments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $modulePermissions = collect(config('modules.list'))->pluck('permission')->all();

        $count = 0;

        Employee::with('user', 'department.modules')->chunk(200, function ($employees) use ($modulePermissions, &$count) {
            foreach ($employees as $employee) {
                $user = $employee->user;

                if (! $user) {
                    continue;
                }

                $user->update(['department_id' => $employee->department_id]);

                $grantedPermissions = $employee->department
                    ? collect($employee->department->moduleCodes())
                        ->map(fn (string $code) => config("modules.list.{$code}.permission"))
                        ->filter()
                        ->all()
                    : [];

                $user->revokePermissionTo(array_diff($modulePermissions, $grantedPermissions));

                if ($grantedPermissions) {
                    $user->givePermissionTo($grantedPermissions);
                }

                $count++;
            }
        });

        $this->info("Synced department & module permissions for {$count} user(s).");
    }
}
