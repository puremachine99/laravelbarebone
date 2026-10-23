<?php

namespace App\Livewire\Admin;

use App\Concerns\WithPerPage;
use App\Support\PerPage;
use App\Support\ResourcePermissions;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleManagement extends Component
{
    use WithPagination;
    use WithPerPage;

    public string $search = '';

    // Form fields
    public ?int $roleId = null;

    public string $roleName = '';

    public array $rolePermissions = [];

    public bool $selectAll = false;

    public bool $isEdit = false;

    public bool $showRoleModal = false;

    public bool $showDeleteModal = false;

    public ?int $deleteTargetId = null;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    /**
     * Modules exposed in the "Data Master" section instead of "Modul Aplikasi",
     * to avoid listing the same permission twice in the form.
     *
     * @var array<int, string>
     */
    protected const DATA_MASTER_MODULE_PERMISSIONS = ['access-internal-org'];

    /**
     * Permissions tied 1:1 to a business module (department-scoped access).
     *
     * @return array<string, array{label: string, description: string, icon: string}>
     */
    public function modulePermissions(): array
    {
        return collect(config('modules.list'))
            ->reject(fn (array $module) => in_array($module['permission'], self::DATA_MASTER_MODULE_PERMISSIONS, true))
            ->mapWithKeys(fn (array $module) => [
                $module['permission'] => [
                    'label' => $module['label'],
                    'description' => $module['description'],
                    'icon' => $module['icon'],
                ],
            ])
            ->all();
    }

    /**
     * Cross-cutting permissions not tied to a specific department/module.
     *
     * @return array<string, array{label: string, description: string, icon: string}>
     */
    public function otherPermissions(): array
    {
        return [];
    }

    /**
     * Data Master menu access. Mirrors the sidebar's "Data Master" gates,
     * which reuse the same permissions as their related business module.
     *
     * @return array<string, array{label: string, description: string, icon: string}>
     */
    public function dataMasterPermissions(): array
    {
        return [
            'access-internal-org' => ['label' => 'Internal', 'description' => 'Departments, positions, employees, and role management.', 'icon' => 'building-office'],
        ];
    }

    /**
     * All assignable permission keys shown across the form's checkbox sections.
     *
     * @return array<int, string>
     */
    public function allAssignablePermissions(): array
    {
        return collect($this->modulePermissions())
            ->keys()
            ->merge(collect($this->otherPermissions())->keys())
            ->merge(collect($this->dataMasterPermissions())->keys())
            ->merge(ResourcePermissions::names())
            ->unique()
            ->values()
            ->all();
    }

    public function updatedSelectAll(bool $value): void
    {
        $this->rolePermissions = $value ? $this->allAssignablePermissions() : [];
    }

    public static function permissionLabel(string $name): string
    {
        $catalog = collect(config('modules.list'))->mapWithKeys(fn (array $m) => [$m['permission'] => $m['label']]);

        $labels = $catalog->merge([
            'view-executive-dashboard' => 'Executive Dashboard',
            'manage-talent-okr' => 'Talent Leaderboard',
            'manage-chat' => 'Nakama Chat',
            'manage-ai-center' => 'AI Center',
            'modify-master-data' => 'Modify Master Data',
            'verify-manager' => 'Verify Manager',
        ]);

        return $labels->get($name) ?? ResourcePermissions::label($name) ?? $name;
    }

    /**
     * Human-readable breakdown of what a role can do, for the expandable row on the roles list.
     *
     * @return array{groups: array<int, array{label: string, icon: string, section: string, granted: int, total: int, resources: array<int, array{label: string, actions: array<int, string>}>}>, other: array<int, string>, moduleCount: int, granted: int, total: int, readOnly: bool}
     */
    public function roleSummary(Role $role): array
    {
        $held = $role->permissions->pluck('name')->flip();
        $actionLabels = ResourcePermissions::actionLabels();

        $groups = collect(ResourcePermissions::groups())
            ->filter(fn (array $group) => $held->has($group['module']))
            ->map(function (array $group) use ($held, $actionLabels) {
                $resources = collect($group['resources'])->map(fn (array $resource, string $key) => [
                    'label' => $resource['label'],
                    'actions' => collect($resource['actions'])
                        ->filter(fn (string $action) => $held->has("{$key}.{$action}"))
                        ->map(fn (string $action) => $actionLabels[$action])
                        ->values()
                        ->all(),
                ])->values();

                return [
                    'label' => $group['label'],
                    'icon' => $group['icon'],
                    'section' => $group['section'],
                    'granted' => $resources->filter(fn (array $resource) => $resource['actions'] !== [])->count(),
                    'total' => $resources->count(),
                    'resources' => $resources->all(),
                ];
            })
            ->values()
            ->all();

        $other = collect($this->otherPermissions())
            ->filter(fn (array $meta, string $permission) => $held->has($permission))
            ->pluck('label')
            ->values()
            ->all();

        $assignable = $this->allAssignablePermissions();
        $resourcePermissions = $held->keys()->filter(fn (string $name) => str_contains($name, '.'));

        return [
            'groups' => $groups,
            'other' => $other,
            'moduleCount' => collect(ResourcePermissions::groups())->pluck('module')->unique()->filter(fn (string $module) => $held->has($module))->count(),
            'granted' => $held->keys()->intersect($assignable)->count(),
            'total' => count($assignable),
            'readOnly' => $resourcePermissions->isNotEmpty() && $resourcePermissions->every(fn (string $name) => str_ends_with($name, '.view')),
        ];
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function resetForm()
    {
        $this->reset(['roleId', 'roleName', 'rolePermissions', 'selectAll', 'isEdit']);
        $this->resetValidation();
    }

    public function edit(int $id)
    {
        $this->resetValidation();
        $role = Role::findOrFail($id);
        $this->roleId = $role->id;
        $this->roleName = $role->name;
        $this->rolePermissions = $role->permissions->pluck('name')->toArray();
        $this->selectAll = empty(array_diff($this->allAssignablePermissions(), $this->rolePermissions));
        $this->isEdit = true;

        $this->showRoleModal = true;
    }

    public function saveRole()
    {
        $rules = [
            'roleName' => [
                'required',
                'string',
                'max:255',
                $this->roleId ? 'unique:roles,name,'.$this->roleId : 'unique:roles,name',
            ],
        ];

        $this->validate($rules, [
            'roleName.required' => 'Role name is required.',
            'roleName.unique' => 'This role name is already taken.',
        ]);

        $isEdit = $this->isEdit;

        if ($isEdit) {
            $role = Role::findOrFail($this->roleId);
            $role->update(['name' => $this->roleName]);
        } else {
            $role = Role::create(['name' => $this->roleName]);
        }

        // Sync Spatie Permissions to this Role
        $allowed = [...$this->allAssignablePermissions(), ...$role->permissions->pluck('name')->all()];

        $permissions = collect(ResourcePermissions::normalize($this->rolePermissions))
            ->intersect($allowed)
            ->map(fn (string $name) => Permission::findOrCreate($name))
            ->all();

        $role->syncPermissions($permissions);

        $this->resetForm();
        $this->showRoleModal = false;

        session()->flash('success', $isEdit ? 'Role permissions saved.' : 'New role created.');
    }

    public function confirmDelete(int $id)
    {
        $this->deleteTargetId = $id;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        if ($this->deleteTargetId) {
            $role = Role::findOrFail($this->deleteTargetId);
            $role->delete();
            $this->deleteTargetId = null;
        }

        $this->showDeleteModal = false;
        session()->flash('error', 'Role deleted.');
    }

    public function render()
    {
        $query = Role::query()->with('permissions')->withCount('users');

        if (! empty($this->search)) {
            $query->where('name', 'like', '%'.$this->search.'%');
        }

        $roles = $query->paginate(PerPage::resolve($this->perPage));

        return view('livewire.admin.role-management', [
            'roles' => $roles,
            'otherPermissions' => $this->otherPermissions(),
            'groupsBySection' => collect(ResourcePermissions::groups())->groupBy('section')->map->all()->all(),
            'actionLabels' => ResourcePermissions::actionLabels(),
        ]);
    }
}
