<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class RoleManagement extends Component
{
    use WithPagination;

    public string $search = '';

    // Form fields
    public ?int $roleId = null;

    public string $roleName = '';

    public array $rolePermissions = [];

    public bool $isEdit = false;

    public bool $showRoleModal = false;

    public bool $showDeleteModal = false;

    public ?int $deleteTargetId = null;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    /**
     * Permissions tied 1:1 to a business module (department-scoped access).
     *
     * @return array<string, array{label: string, description: string, icon: string}>
     */
    public function modulePermissions(): array
    {
        return collect(config('modules.list'))
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
        return [
            'view-executive-dashboard' => ['label' => 'Executive Dashboard', 'description' => 'Ringkasan performa perusahaan.', 'icon' => 'presentation-chart-line'],
            'manage-talent-okr' => ['label' => 'Talent Leaderboard', 'description' => 'OKR dan leaderboard talent.', 'icon' => 'trophy'],
            'manage-chat' => ['label' => 'Nakama Chat', 'description' => 'Chat internal tim.', 'icon' => 'chat-bubble-left-right'],
            'manage-ai-center' => ['label' => 'AI Center', 'description' => 'Fitur AI internal.', 'icon' => 'sparkles'],
        ];
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

        return $labels->get($name, $name);
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function resetForm()
    {
        $this->reset(['roleId', 'roleName', 'rolePermissions', 'isEdit']);
        $this->resetValidation();
    }

    public function edit(int $id)
    {
        $this->resetValidation();
        $role = Role::findOrFail($id);
        $this->roleId = $role->id;
        $this->roleName = $role->name;
        $this->rolePermissions = $role->permissions->pluck('name')->toArray();
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
            'roleName.required' => 'Nama role wajib diisi.',
            'roleName.unique' => 'Nama role sudah terdaftar.',
        ]);

        $isEdit = $this->isEdit;

        if ($isEdit) {
            $role = Role::findOrFail($this->roleId);
            $role->update(['name' => $this->roleName]);
        } else {
            $role = Role::create(['name' => $this->roleName]);
        }

        // Sync Spatie Permissions to this Role
        $role->syncPermissions($this->rolePermissions);

        $this->resetForm();
        $this->showRoleModal = false;

        session()->flash('success', $isEdit ? 'Hak akses peran berhasil disimpan.' : 'Peran baru berhasil didaftarkan.');
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
        session()->flash('error', 'Peran berhasil dihapus.');
    }

    public function render()
    {
        $query = Role::query()->with('permissions');

        if (! empty($this->search)) {
            $query->where('name', 'like', '%'.$this->search.'%');
        }

        $roles = $query->paginate(10);

        return view('livewire.admin.role-management', [
            'roles' => $roles,
            'modulePermissions' => $this->modulePermissions(),
            'otherPermissions' => $this->otherPermissions(),
        ]);
    }
}
