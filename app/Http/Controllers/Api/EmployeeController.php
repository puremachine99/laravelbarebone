<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Internal employee directory for the AI service (service bearer token, no user assertion).
 */
class EmployeeController extends Controller
{
    private const RELATIONS = ['user', 'department', 'region', 'positionStruktural', 'positionFungsional', 'positionTeknis'];

    /**
     * List/search employees. "q" is a free-text match on name, email, username and NIS; the rest are per-column
     * filters. Paginated.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(EmployeeStatus::class)],
            'employment_status' => ['nullable', 'string', 'max:50'],
            'sort' => ['nullable', Rule::in(['name', 'nis'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $like = fn (string $value): string => '%'.addcslashes(mb_strtolower($value), '%_\\').'%';

        $employees = Employee::query()
            ->with(self::RELATIONS)
            ->join('users', 'users.id', '=', 'employees.user_id')
            ->select('employees.*')
            ->when($validated['q'] ?? null, fn ($query, $keyword) => $query->where(fn ($match) => $match
                ->whereRaw('LOWER(users.name) LIKE ?', [$like($keyword)])
                ->orWhereRaw('LOWER(users.email) LIKE ?', [$like($keyword)])
                ->orWhereRaw('LOWER(users.username) LIKE ?', [$like($keyword)])
                ->orWhereRaw('LOWER(employees.nis) LIKE ?', [$like($keyword)])))
            ->when($validated['department'] ?? null, fn ($query, $department) => $query->whereHas('department', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$like($department)])))
            ->when($validated['region'] ?? null, fn ($query, $region) => $query->whereHas('region', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$like($region)])))
            ->when($validated['position'] ?? null, fn ($query, $position) => $query->where(fn ($match) => $match
                ->whereHas('positionStruktural', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$like($position)]))
                ->orWhereHas('positionFungsional', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$like($position)]))
                ->orWhereHas('positionTeknis', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$like($position)]))))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('employees.status', $status))
            ->when($validated['employment_status'] ?? null, fn ($query, $value) => $query->whereRaw('LOWER(employees.employment_status) = ?', [mb_strtolower($value)]))
            ->orderBy(($validated['sort'] ?? 'name') === 'nis' ? 'employees.nis' : 'users.name', $validated['direction'] ?? 'asc')
            ->orderBy('employees.id')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return EmployeeResource::collection($employees);
    }

    public function show(Employee $employee): EmployeeResource
    {
        return new EmployeeResource($employee->load(self::RELATIONS));
    }
}
