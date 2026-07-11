<div class="space-y-6">
    <flux:card class="p-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <flux:heading size="lg" class="font-bold">Role & Permission Settings</flux:heading>
                <flux:subheading>Konfigurasi hak akses modul yang diperbolehkan untuk setiap Peran (Role).</flux:subheading>
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama role..." icon="magnifying-glass" class="w-64" />
                <flux:button variant="primary" icon="plus" wire:click="resetForm" x-on:click="flux.modal('role-modal').show()">Peran Baru</flux:button>
            </div>
        </div>

        <flux:separator variant="subtle" />

        <!-- Roles Table -->
        <flux:table :paginate="$roles" hoverable>
            <flux:table.columns>
                <flux:table.column>Nama Peran (Role)</flux:table.column>
                <flux:table.column>Hak Akses</flux:table.column>
                <flux:table.column align="right">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($roles as $role)
                    <flux:table.row :key="$role->id" class="cursor-pointer" wire:click="edit({{ $role->id }})">
                        <flux:table.cell class="font-semibold text-base-content">
                            {{ $role->name }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($role->name === 'Super Admin')
                                <flux:badge size="sm" color="amber" variant="pill">Full Access — Semua Modul</flux:badge>
                            @else
                                <div class="flex flex-wrap gap-1.5 max-w-2xl">
                                    @if ($role->name === 'Direksi')
                                        <flux:badge size="sm" color="amber" variant="pill">Read Only</flux:badge>
                                    @endif
                                    @forelse ($role->permissions as $perm)
                                        <flux:badge size="sm" color="indigo" variant="pill">
                                            {{ \App\Livewire\Admin\RoleManagement::permissionLabel($perm->name) }}
                                        </flux:badge>
                                    @empty
                                        <span class="text-xs text-base-content/40">Tidak ada hak akses spesifik</span>
                                    @endforelse
                                </div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="right" wire:click.stop>
                            <flux:button variant="ghost" size="sm" icon="pencil-square" inset="top bottom" wire:click="edit({{ $role->id }})" />
                            @if ($role->name !== 'Super Admin')
                                <flux:button variant="danger" size="sm" icon="trash" inset="top bottom" wire:click="confirmDelete({{ $role->id }})" />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="text-center py-6 text-base-content/50">
                            Tidak ada data peran ditemukan.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <!-- Role Create/Edit Modal -->
    <flux:modal name="role-modal" class="md:w-[640px]">
        <form wire:submit.prevent="saveRole" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $isEdit ? 'Edit Peran & Hak Akses' : 'Tambah Peran Baru' }}</flux:heading>
                <flux:subheading>Tentukan nama peran serta modul yang diijinkan untuk peran ini.</flux:subheading>
            </div>

            <flux:separator variant="subtle" />

            <!-- Role Name -->
            <flux:field>
                <flux:label>Nama Peran (Role Name)</flux:label>
                <flux:input wire:model="roleName" placeholder="Cth: Partnership Executive" required :disabled="$roleName === 'Super Admin'" />
                <flux:error name="roleName" />
            </flux:field>

            <!-- Module access -->
            <div class="space-y-3">
                <flux:label class="font-medium text-base-content/90">Modul Aplikasi (Department Access)</flux:label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    @foreach ($modulePermissions as $permission => $meta)
                        <label class="flex items-start gap-3 p-3 rounded-lg border border-base-300 bg-base-100 hover:bg-base-200 cursor-pointer">
                            <input type="checkbox" wire:model="rolePermissions" value="{{ $permission }}" class="mt-0.5 rounded border-base-300 text-indigo-600 focus:ring-indigo-500" :disabled="$roleName === 'Super Admin'" />
                            <div class="flex items-start gap-2.5">
                                <flux:icon name="{{ $meta['icon'] }}" class="size-5 shrink-0 text-indigo-500 mt-0.5" />
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
                <flux:label class="font-medium text-base-content/90">Fitur Lainnya</flux:label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    @foreach ($otherPermissions as $permission => $meta)
                        <label class="flex items-start gap-3 p-3 rounded-lg border border-base-300 bg-base-100 hover:bg-base-200 cursor-pointer">
                            <input type="checkbox" wire:model="rolePermissions" value="{{ $permission }}" class="mt-0.5 rounded border-base-300 text-indigo-600 focus:ring-indigo-500" :disabled="$roleName === 'Super Admin'" />
                            <div class="flex items-start gap-2.5">
                                <flux:icon name="{{ $meta['icon'] }}" class="size-5 shrink-0 text-indigo-500 mt-0.5" />
                                <div>
                                    <div class="text-sm font-semibold text-base-content/90">{{ $meta['label'] }}</div>
                                    <div class="text-xs text-base-content/50">{{ $meta['description'] }}</div>
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan Data</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Confirm Delete Modal -->
    <flux:modal name="confirm-delete-role" class="max-w-sm">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Hapus Peran?</flux:heading>
                <flux:text class="mt-1">Menghapus peran ini dapat mempengaruhi hak akses pengguna yang memilikinya. Tindakan ini tidak bisa dibatalkan.</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">Ya, Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
