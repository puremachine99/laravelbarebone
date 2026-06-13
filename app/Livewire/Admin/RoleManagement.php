<?php

namespace App\Livewire\Admin;

use Flux\Flux;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;
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

    public ?int $deleteTargetId = null;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

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

        Flux::modal('role-modal')->show();
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

        if ($this->isEdit) {
            $role = Role::findOrFail($this->roleId);
            $role->update(['name' => $this->roleName]);
        } else {
            $role = Role::create(['name' => $this->roleName]);
        }

        // Sync Spatie Permissions to this Role
        $role->syncPermissions($this->rolePermissions);

        $this->resetForm();
        Flux::modal('role-modal')->close();

        Flux::toast(
            variant: 'success',
            heading: $this->isEdit ? 'Peran Diperbarui' : 'Peran Ditambahkan',
            text: $this->isEdit ? 'Hak akses peran berhasil disimpan.' : 'Peran baru berhasil didaftarkan.',
        );
    }

    public function confirmDelete(int $id)
    {
        $this->deleteTargetId = $id;
        Flux::modal('confirm-delete-role')->show();
    }

    public function delete()
    {
        if ($this->deleteTargetId) {
            $role = Role::findOrFail($this->deleteTargetId);
            $role->delete();
            $this->deleteTargetId = null;
        }

        Flux::modal('confirm-delete-role')->close();
        Flux::toast(variant: 'danger', text: 'Peran berhasil dihapus.');
    }

    public function render()
    {
        $query = Role::query()->with('permissions');

        if (! empty($this->search)) {
            $query->where('name', 'like', '%'.$this->search.'%');
        }

        $roles = $query->paginate(10);
        $allPermissions = Permission::orderBy('name')->get();

        return view('livewire.admin.role-management', [
            'roles' => $roles,
            'allPermissions' => $allPermissions,
        ]);
    }
}
