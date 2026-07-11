<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $guarded = ['id'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(DepartmentModule::class);
    }

    /**
     * @return array<int, string>
     */
    public function moduleCodes(): array
    {
        return $this->modules->pluck('module_code')->all();
    }

    public function hasModule(string $code): bool
    {
        return in_array($code, $this->moduleCodes(), true);
    }
}
