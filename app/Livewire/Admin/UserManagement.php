<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Flux\Flux;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class UserManagement extends Component
{
    use WithPagination;

    public string $search = '';

    public ?User $selectedUser = null;

    public array $userRoles = [];

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openPermissions(int $userId)
    {
        $this->selectedUser = User::with(['employee.department', 'employee.positionStruktural'])->findOrFail($userId);
        $this->userRoles = $this->selectedUser->roles->pluck('name')->toArray();

        Flux::modal('permissions-modal')->show();
    }

    public function savePermissions()
    {
        if (! $this->selectedUser) {
            return;
        }

        // Sync Spatie Roles only
        $this->selectedUser->syncRoles($this->userRoles);

        // Flash message
        session()->flash('success', "Hak akses untuk {$this->selectedUser->name} berhasil diperbarui.");

        Flux::modal('permissions-modal')->close();
        $this->selectedUser = null;
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

        $users = $query->paginate(10);
        $allRoles = Role::all();

        return view('livewire.admin.user-management', [
            'users' => $users,
            'allRoles' => $allRoles,
        ]);
    }
}
