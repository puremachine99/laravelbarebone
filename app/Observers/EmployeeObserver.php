<?php

namespace App\Observers;

use App\Models\Employee;

class EmployeeObserver
{
    /**
     * Handle the Employee "created" event.
     */
    public function created(Employee $employee): void
    {
        $this->syncUserDepartment($employee);
    }

    /**
     * Handle the Employee "updated" event.
     */
    public function updated(Employee $employee): void
    {
        if ($employee->wasChanged('department_id') || $employee->wasChanged('user_id')) {
            $this->syncUserDepartment($employee);
        }
    }

    /**
     * Keep the linked User's department_id and module permissions in sync with
     * this Employee's department, so access defaults to "what their department allows".
     */
    protected function syncUserDepartment(Employee $employee): void
    {
        $user = $employee->user;

        if (! $user) {
            return;
        }

        $user->update(['department_id' => $employee->department_id]);

        $modulePermissions = collect(config('modules.list'))->pluck('permission')->all();

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
    }
}
