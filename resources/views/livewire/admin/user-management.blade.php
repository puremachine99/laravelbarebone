<div class="p-6 lg:p-8 space-y-6">
    @if (session()->has('success'))
        <div class="alert alert-success shadow-sm">
            <x-icon name="o-check-circle" class="w-5 h-5 text-white" />
            <span class="text-white">{{ session('success') }}</span>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error shadow-sm">
            <x-icon name="o-exclamation-triangle" class="w-5 h-5 text-white" />
            <span class="text-white">{{ session('error') }}</span>
        </div>
    @endif

    <div class="card bg-base-100 border border-base-300 p-6 space-y-6 shadow-sm rounded-2xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-50 dark:bg-indigo-950/50 rounded-lg">
                    <x-icon name="o-shield-check" class="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div>
                    <h2 class="text-xl font-bold text-base-content">User & Permission Management</h2>
                    <p class="text-sm text-base-content/50 mt-1">Kelola data pengguna serta hak akses role & permission secara dinamis</p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto">
                <x-input wire:model.live.debounce.300ms="search" placeholder="Cari nama, email, atau NIS..." icon="o-magnifying-glass" class="w-full sm:w-72" />
                <x-select wire:model.live="departmentId" :options="$departments" placeholder="Semua Departemen" placeholder-value="" class="w-full sm:w-56" />
                <x-button variant="primary" icon="o-plus" label="Tambah User" wire:click="openCreate" />
            </div>
        </div>

        <div class="divider my-0"></div>

        <!-- Users Table -->
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full text-base-content/70">
                <thead>
                    <tr class="text-base-content/50 border-b border-base-300">
                        <th class="py-3">Nama & Email</th>
                        <th>Departemen</th>
                        <th>Jabatan Struktural</th>
                        <th>Roles Aktif</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr class="border-b border-base-300 hover:bg-base-200 cursor-pointer" wire:click="openEdit({{ $user->id }})">
                            <td class="py-3">
                                <div class="font-medium text-base-content">{{ $user->name }}</div>
                                <div class="text-xs text-base-content/50">{{ $user->email }}</div>
                            </td>
                            <td>{{ $user->employee?->department?->name ?? '-' }}</td>
                            <td>{{ $user->employee?->positionStruktural?->name ?? '-' }}</td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($user->roles as $role)
                                        <span class="badge badge-sm badge-outline badge-primary">{{ $role->name }}</span>
                                    @empty
                                        <span class="text-xs text-base-content/40">Tidak ada role</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="text-right" wire:click.stop>
                                <div class="flex gap-2 justify-end">
                                    <x-button icon="o-pencil-square" class="btn-ghost btn-xs text-base-content/50" wire:click="openEdit({{ $user->id }})" />
                                    @if ($user->id !== auth()->id())
                                        <x-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:click="confirmDelete({{ $user->id }})" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-base-content/50 py-8">
                                Tidak ada data pengguna ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $users->links() }}
        </div>
    </div>

    <!-- Create/Edit User Modal -->
    <x-modal wire:model="showUserModal" title="{{ $isEdit ? 'Edit Pengguna' : 'Tambah Pengguna Baru' }}" subtitle="Kelola data akun serta peran dan hak akses dinamis untuk pengguna.">
        <form wire:submit.prevent="saveUser" class="space-y-4">
            @if ($isEdit && $selectedUser)
                <div class="card bg-base-200/50 border border-base-300 p-3 rounded-lg text-xs text-base-content/50 space-y-1">
                    <div>Struktural: {{ $selectedUser->employee?->positionStruktural?->name ?? '-' }}</div>
                    <div>Departemen (Employee): {{ $selectedUser->employee?->department?->name ?? '-' }}</div>
                </div>
            @endif

            <x-input label="Nama" wire:model="name" placeholder="Nama lengkap" required />
            <x-input label="Email" type="email" wire:model="email" placeholder="nama@email.com" required />
            <x-input
                label="{{ $isEdit ? 'Password Baru (opsional)' : 'Password' }}"
                type="password"
                wire:model="password"
                placeholder="{{ $isEdit ? 'Kosongkan jika tidak diubah' : 'Minimal 8 karakter' }}"
            />
            <x-select label="Departemen" wire:model="formDepartmentId" :options="$departments" placeholder="Tanpa Departemen" placeholder-value="" />

            <div class="divider my-1"></div>

            <!-- Spatie Roles -->
            <div class="space-y-2">
                <div class="text-sm font-semibold text-base-content/90">Pilih Peran (Roles)</div>
                <div class="grid grid-cols-1 gap-2">
                    @foreach ($allRoles as $role)
                        <label class="flex items-center gap-3 p-3 rounded-lg border border-base-300 bg-base-100 hover:bg-base-200 cursor-pointer">
                            <input type="checkbox" wire:model="userRoles" value="{{ $role->name }}" class="checkbox checkbox-sm checkbox-primary" />
                            <span class="text-sm font-medium text-base-content/90">{{ $role->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-base-300">
                <x-button label="Batal" wire:click="$set('showUserModal', false)" class="btn-ghost" />
                <x-button label="Simpan Perubahan" type="submit" class="btn-primary" />
            </div>
        </form>
    </x-modal>

    <!-- Confirm Delete Modal -->
    <x-modal wire:model="showDeleteModal" title="Hapus Pengguna?" subtitle="Menghapus pengguna ini akan menghilangkan akses login dan semua peran yang dimilikinya. Tindakan ini tidak bisa dibatalkan.">
        <div class="flex justify-end gap-2 pt-4">
            <x-button label="Batal" wire:click="$set('showDeleteModal', false)" class="btn-ghost" />
            <x-button label="Ya, Hapus" wire:click="delete" class="btn-error text-white" />
        </div>
    </x-modal>
</div>
