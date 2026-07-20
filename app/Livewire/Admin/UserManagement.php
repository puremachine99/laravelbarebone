<?php

namespace App\Livewire\Admin;

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class UserManagement extends Component
{
    use WithPagination;

    public string $search = '';

    public string $departmentId = '';

    public ?User $selectedUser = null;

    public array $userRoles = [];

    // Form fields
    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $formDepartmentId = '';

    public bool $isEdit = false;

    public bool $showUserModal = false;

    public bool $showDeleteModal = false;

    public ?int $deleteTargetId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'departmentId' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingDepartmentId()
    {
        $this->resetPage();
    }

    public function resetForm()
    {
        $this->reset(['userId', 'name', 'email', 'password', 'formDepartmentId', 'userRoles', 'isEdit', 'selectedUser']);
        $this->resetValidation();
    }

    public function openCreate()
    {
        $this->resetForm();

        $this->showUserModal = true;
    }

    public function openEdit(int $userId)
    {
        $this->resetForm();

        $user = User::with(['roles', 'employee.department', 'employee.positionStruktural'])->findOrFail($userId);
        $this->selectedUser = $user;
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->formDepartmentId = (string) $user->department_id;
        $this->userRoles = $user->roles->pluck('name')->toArray();
        $this->isEdit = true;

        $this->showUserModal = true;
    }

    public function saveUser()
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                $this->userId ? Rule::unique('users', 'email')->ignore($this->userId) : Rule::unique('users', 'email'),
            ],
            'password' => [$this->isEdit ? 'nullable' : 'required', 'string', 'min:8'],
            'formDepartmentId' => ['nullable', 'exists:departments,id'],
        ];

        $this->validate($rules, [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email sudah terdaftar.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'department_id' => $this->formDepartmentId !== '' ? $this->formDepartmentId : null,
        ];

        if (filled($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->isEdit) {
            $user = User::findOrFail($this->userId);
            $user->update($data);
        } else {
            $data['password'] = $data['password'] ?? Hash::make($this->password);
            $user = User::create($data);
        }

        $user->syncRoles($this->userRoles);

        $isEdit = $this->isEdit;
        $this->resetForm();
        $this->showUserModal = false;

        session()->flash('success', $isEdit ? 'Data dan hak akses pengguna berhasil diperbarui.' : 'Pengguna baru berhasil didaftarkan.');
    }

    public function confirmDelete(int $id)
    {
        $this->deleteTargetId = $id;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        if ($this->deleteTargetId && $this->deleteTargetId !== auth()->id()) {
            User::findOrFail($this->deleteTargetId)->delete();
        }

        $this->deleteTargetId = null;
        $this->showDeleteModal = false;
        session()->flash('error', 'Pengguna berhasil dihapus.');
    }

    public function render()
    {
        $query = User::query()->with(['employee.department', 'employee.positionStruktural']);

        if (! empty($this->search)) {
            $searchTerm = '%'.$this->search.'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)
                    ->orWhereHas('employee', function ($sub) use ($searchTerm) {
                        $sub->where('nis', 'like', $searchTerm);
                    });
            });
        }

        if (! empty($this->departmentId)) {
            $query->whereHas('employee', function ($sub) {
                $sub->where('department_id', $this->departmentId);
            });
        }

        $users = $query->paginate(10);
        $allRoles = Role::all();
        $departments = Department::orderBy('name')->get();

        return view('livewire.admin.user-management', [
            'users' => $users,
            'allRoles' => $allRoles,
            'departments' => $departments,
        ]);
    }
}
