<?php

namespace App\Livewire\Admin;

use App\Actions\Users\InviteUser;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        $this->reset(['userId', 'name', 'email', 'formDepartmentId', 'userRoles', 'isEdit', 'selectedUser']);
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

    public function saveUser(InviteUser $inviteUser)
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
            'formDepartmentId' => ['nullable', 'exists:departments,id'],
        ];

        $this->validate($rules, [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email sudah terdaftar.',
        ]);

        $departmentId = $this->formDepartmentId !== '' ? (int) $this->formDepartmentId : null;

        if ($this->isEdit) {
            $user = User::findOrFail($this->userId);
            $user->update([
                'name' => $this->name,
                'email' => $this->email,
                'department_id' => $departmentId,
            ]);
            $user->syncRoles($this->userRoles);

            $message = 'Data dan hak akses pengguna berhasil diperbarui.';
        } else {
            $inviteUser->handle($this->name, $this->email, $departmentId, $this->userRoles, auth()->user());

            $message = "Undangan berhasil dikirim ke {$this->email}.";
        }

        $this->resetForm();
        $this->showUserModal = false;

        session()->flash('success', $message);
    }

    public function resendInvite(InviteUser $inviteUser, int $userId): void
    {
        $user = User::findOrFail($userId);

        if (! $user->isInvited()) {
            return;
        }

        $inviteUser->resend($user);

        session()->flash('success', 'Undangan berhasil dikirim ulang.');
    }

    public function suspend(int $userId): void
    {
        if ($userId === auth()->id()) {
            return;
        }

        $user = User::findOrFail($userId);
        $user->update(['status' => UserStatus::Suspended]);

        DB::table('sessions')->where('user_id', $userId)->delete();

        session()->flash('error', 'Pengguna berhasil dinonaktifkan dan sesi aktifnya diputus.');
    }

    public function activate(int $userId): void
    {
        User::whereKey($userId)->update(['status' => UserStatus::Active]);

        session()->flash('success', 'Pengguna berhasil diaktifkan kembali.');
    }

    public function confirmDelete(int $id)
    {
        $this->deleteTargetId = $id;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        if ($this->deleteTargetId && $this->deleteTargetId !== auth()->id()) {
            DB::table('sessions')->where('user_id', $this->deleteTargetId)->delete();
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
