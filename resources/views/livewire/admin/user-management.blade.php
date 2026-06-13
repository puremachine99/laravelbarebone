<div class="p-6 lg:p-8 space-y-6">
 <flux:card class="p-6 space-y-6 bg-base-100/50 border-base-300">
 <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
 <div class="flex items-center gap-3">
 <div class="p-2 bg-indigo-50 dark:bg-indigo-950/50 rounded-lg">
 <flux:icon name="shield-check" class="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
 </div>
 <div>
 <flux:heading level="2" class="text-xl font-semibold">User & Permission Management</flux:heading>
 <flux:text class="text-sm text-base-content/50">Kelola hak akses role & permission karyawan secara dinamis</flux:text>
 </div>
 </div>
 
 <div class="w-full sm:w-72">
 <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama, email, atau NIS..." icon="magnifying-glass" />
 </div>
 </div>

 <flux:separator />

 @if (session()->has('success'))
 <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/50 text-emerald-800 dark:text-emerald-300 text-sm font-medium">
 {{ session('success') }}
 </div>
 @endif

 <!-- Users Table -->
 <flux:table :paginate="$users" striped hoverable>
 <flux:table.columns>
 <flux:table.column>Nama & Email</flux:table.column>
 <flux:table.column>Departemen</flux:table.column>
 <flux:table.column>Jabatan Struktural</flux:table.column>
 <flux:table.column>Roles Aktif</flux:table.column>
 <flux:table.column align="right">Aksi</flux:table.column>
 </flux:table.columns>

 <flux:table.rows>
 @forelse ($users as $user)
 <flux:table.row :key="$user->id" class="cursor-pointer" wire:click="openPermissions({{ $user->id }})">
 <flux:table.cell>
 <div class="font-medium text-base-content">{{ $user->name }}</div>
 <div class="text-xs text-base-content/50">{{ $user->email }}</div>
 </flux:table.cell>
 <flux:table.cell>
 {{ $user->employee?->department?->name ??'-' }}
 </flux:table.cell>
 <flux:table.cell>
 {{ $user->employee?->positionStruktural?->name ??'-' }}
 </flux:table.cell>
 <flux:table.cell>
 <div class="flex flex-wrap gap-1">
 @forelse ($user->roles as $role)
 <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-900/50">
 {{ $role->name }}
 </span>
 @empty
 <span class="text-xs text-base-content/40">Tidak ada role</span>
 @endforelse
 </div>
 </flux:table.cell>
 <flux:table.cell align="right" wire:click.stop>
 <flux:button variant="ghost" size="sm" icon="pencil-square" inset="top bottom" wire:click="openPermissions({{ $user->id }})" />
 </flux:table.cell>
 </flux:table.row>
 @empty
 <flux:table.row>
 <flux:table.cell colspan="5" class="text-center py-6 text-base-content/50">
 Tidak ada data pengguna ditemukan.
 </flux:table.cell>
 </flux:table.row>
 @endforelse
 </flux:table.rows>
 </flux:table>
 </flux:card>

 <!-- Permissions Modal -->
 <flux:modal name="permissions-modal" class="md:w-[560px]">
 @if ($selectedUser)
 <div class="space-y-4">
 <div>
 <flux:heading level="3" class="text-lg font-bold">Kelola Hak Akses</flux:heading>
 <flux:text class="text-sm">Konfigurasi peran dan hak akses dinamis untuk karyawan.</flux:text>
 </div>

 <flux:card class="p-4 bg-base-100/50 border-base-300 space-y-2">
 <div class="font-semibold text-base-content text-sm">{{ $selectedUser->name }}</div>
 <div class="text-xs text-base-content/50">
 <div>Email: {{ $selectedUser->email }}</div>
 <div>Struktural: {{ $selectedUser->employee?->positionStruktural?->name ??'-' }}</div>
 <div>Departemen: {{ $selectedUser->employee?->department?->name ??'-' }}</div>
 </div>
 </flux:card>

 <flux:separator />

 <form wire:submit.prevent="savePermissions" class="space-y-6">
 <!-- Spatie Roles -->
 <div class="space-y-3">
 <flux:heading level="4" class="text-sm font-semibold text-base-content/90">Pilih Peran Utama (Roles)</flux:heading>
 <div class="grid grid-cols-1 gap-2">
 @foreach ($allRoles as $role)
 <label class="flex items-center gap-3 p-3 rounded-lg border border-base-300 bg-base-100 hover:bg-base-200 cursor-pointer">
 <input type="checkbox" wire:model="userRoles" value="{{ $role->name }}" class="rounded border-base-300 text-indigo-600 focus:ring-indigo-500" />
 <div>
 <div class="text-sm font-medium text-base-content/90">{{ $role->name }}</div>
 </div>
 </label>
 @endforeach
 </div>
 </div>

 <div class="flex items-center justify-end gap-3 pt-4 border-t border-base-300">
 <flux:modal.close>
 <flux:button variant="ghost">Batal</flux:button>
 </flux:modal.close>
 <flux:button type="submit" variant="filled" class="bg-indigo-600 hover:bg-indigo-700 text-white">Simpan Perubahan</flux:button>
 </div>
 </form>
 </div>
 @endif
 </flux:modal>
</div>
