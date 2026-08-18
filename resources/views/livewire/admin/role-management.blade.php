<div class="space-y-6">
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
            <div>
                <h2 class="text-xl font-bold text-base-content">Role & Permission Settings</h2>
                <p class="text-sm text-base-content/50 mt-1">Konfigurasi hak akses modul yang diperbolehkan untuk setiap Peran (Role).</p>
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                <x-input wire:model.live.debounce.300ms="search" placeholder="Cari nama role..." icon="o-magnifying-glass" clearable />
                <x-button variant="primary" icon="o-plus" size="btn-sm" label="Peran Baru" wire:click="resetForm(); $set('showRoleModal', true);" />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table table-zebra w-full text-base-content/70">
                <thead>
                    <tr class="text-base-content/50 border-b border-base-300">
                        <th class="py-3">Nama Peran (Role)</th>
                        <th>Hak Akses</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr class="border-b border-base-300 hover:bg-base-200 cursor-pointer" wire:click="edit({{ $role->id }})">
                            <td class="font-semibold text-base-content py-3">{{ $role->name }}</td>
                            <td>
                                @if ($role->name === 'Super Admin')
                                    <div class="badge badge-sm badge-warning">Full Access — Semua Modul</div>
                                @else
                                    <div class="flex flex-wrap gap-1.5 max-w-2xl">
                                        @if ($role->name === 'Direksi')
                                            <div class="badge badge-sm badge-warning">Read Only</div>
                                        @endif
                                        @forelse ($role->permissions as $perm)
                                            <div class="badge badge-sm badge-info">{{ \App\Livewire\Admin\RoleManagement::permissionLabel($perm->name) }}</div>
                                        @empty
                                            <span class="text-xs text-base-content/40">Tidak ada hak akses spesifik</span>
                                        @endforelse
                                    </div>
                                @endif
                            </td>
                            <td class="text-right" wire:click.stop>
                                <div class="flex gap-2 justify-end">
                                    <x-button icon="o-pencil-square" class="btn-ghost btn-xs text-base-content/50" wire:click="edit({{ $role->id }})" />
                                    @if ($role->name !== 'Super Admin')
                                        <x-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:click="confirmDelete({{ $role->id }})" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-base-content/50 py-8">
                                Tidak ada data peran ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $roles->links() }}
        </div>
    </div>

    <!-- Role Create/Edit Modal -->
    <x-modal wire:model="showRoleModal" title="{{ $isEdit ? 'Edit Peran & Hak Akses' : 'Tambah Peran Baru' }}" subtitle="Tentukan nama peran serta modul yang diijinkan untuk peran ini." box-class="md:w-[640px]">
        <form wire:submit.prevent="saveRole" class="space-y-6">
            <x-input label="Nama Peran (Role Name)" wire:model="roleName" placeholder="Cth: Partnership Executive" required :disabled="$roleName === 'Super Admin'" />

            <!-- Module access -->
            <div class="space-y-3">
                <label class="font-medium text-base-content/90 text-sm">Modul Aplikasi (Department Access)</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    @foreach ($modulePermissions as $permission => $meta)
                        <label class="flex items-start gap-3 p-3 rounded-lg border border-base-300 bg-base-100 hover:bg-base-200 cursor-pointer">
                            <input type="checkbox" wire:model="rolePermissions" value="{{ $permission }}" class="mt-0.5 rounded border-base-300 text-indigo-600 focus:ring-indigo-500" :disabled="$wire.roleName === 'Super Admin'" />
                            <div class="flex items-start gap-2.5">
                                <x-icon name="o-{{ $meta['icon'] }}" class="size-5 shrink-0 text-indigo-500 mt-0.5" />
                                <div>
                                    <div class="text-sm font-semibold text-base-content/90">{{ $meta['label'] }}</div>
                                    <div class="text-xs text-base-content/50">{{ $meta['description'] }}</div>
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Other cross-cutting features -->
            <div class="space-y-3">
                <label class="font-medium text-base-content/90 text-sm">Fitur Lainnya</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    @foreach ($otherPermissions as $permission => $meta)
                        <label class="flex items-start gap-3 p-3 rounded-lg border border-base-300 bg-base-100 hover:bg-base-200 cursor-pointer">
                            <input type="checkbox" wire:model="rolePermissions" value="{{ $permission }}" class="mt-0.5 rounded border-base-300 text-indigo-600 focus:ring-indigo-500" :disabled="$wire.roleName === 'Super Admin'" />
                            <div class="flex items-start gap-2.5">
                                <x-icon name="o-{{ $meta['icon'] }}" class="size-5 shrink-0 text-indigo-500 mt-0.5" />
                                <div>
                                    <div class="text-sm font-semibold text-base-content/90">{{ $meta['label'] }}</div>
                                    <div class="text-xs text-base-content/50">{{ $meta['description'] }}</div>
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
                <x-button label="Batal" wire:click="$set('showRoleModal', false)" class="btn-ghost" />
                <x-button label="Simpan Data" type="submit" class="btn-primary" />
            </div>
        </form>
    </x-modal>

    <!-- Confirm Delete Modal -->
    <x-modal wire:model="showDeleteModal" title="Hapus Peran?" subtitle="Menghapus peran ini dapat mempengaruhi hak akses pengguna yang memilikinya. Tindakan ini tidak bisa dibatalkan.">
        <div class="flex justify-end gap-2 pt-4">
            <x-button label="Batal" wire:click="$set('showDeleteModal', false)" class="btn-ghost" />
            <x-button label="Ya, Hapus" wire:click="delete" class="btn-error text-white" />
        </div>
    </x-modal>
</div>
