<?php

use function Livewire\Volt\{state, mount, computed, usesPagination};
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

usesPagination();

state(['perPage' => 10]);
$updatedPerPage = fn () => $this->resetPage('usersPage');

/**
 * Embedded in the Sentry page; checked here too so the actions are never reachable by anyone but an active
 * Super Admin, whatever page hosts the component.
 */
$ensureSuperAdmin = function () {
 $user = auth()->user();

 abort_unless($user && $user->isActive() && $user->hasRole('Super Admin'), 404);
};

mount(fn () => $this->ensureSuperAdmin());

state([
'search' =>'',
'confirmTargetId' => null,
'showConfirmModal' => false,
]);

$confirmTarget = computed(fn () => $this->confirmTargetId ? User::find($this->confirmTargetId) : null);

$users = computed(function () {
 $search = $this->search;

 return User::query()
 ->where('id','!=', auth()->id())
 ->when($search, fn ($q) =>
 $q->where(fn ($q) =>
 $q->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($search).'%'])
 ->orWhereRaw('LOWER(email) LIKE ?', ['%'.strtolower($search).'%'])
 )
 )
 ->orderBy('name')
 ->paginate(\App\Support\PerPage::resolve($this->perPage), pageName: 'usersPage');
});

$updatedSearch = function () {
 $this->resetPage('usersPage');
};

$confirm = function (int $userId) {
 $this->confirmTargetId = $userId;
 $this->showConfirmModal = true;
};

$cancel = function () {
 $this->confirmTargetId = null;
 $this->showConfirmModal = false;
};

$loginAs = function () {
 $this->ensureSuperAdmin();

 $target = User::findOrFail($this->confirmTargetId);

 if (! $target->isActive()) {
 $this->confirmTargetId = null;
 $this->showConfirmModal = false;
 session()->flash('error',"Tidak bisa login sebagai {$target->name}: akun berstatus \"{$target->status->value}\", bukan aktif.");

 return;
 }

 $admin = auth()->user();

 Log::warning('force_login.impersonate', [
'admin_id' => $admin->id,
'admin_email' => $admin->email,
'target_id' => $target->id,
'target_email' => $target->email,
'ip' => request()->ip(),
'at' => now()->toDateTimeString(),
 ]);

 Auth::login($target);
 session()->regenerate();

 $this->redirect(route('dashboard'), navigate: true);
};

?>
<div class="space-y-6">
 <x-nakama.flash />

 <div class="card bg-base-100 border border-base-300 p-6 space-y-6 shadow-sm rounded-2xl">
 <div>
 <h2 class="text-xl font-bold text-base-content">Force Login</h2>
 <p class="text-sm text-base-content/50 mt-1">
 Masuk secara langsung sebagai user lain untuk keperluan dukungan/debug. Hanya dapat diakses oleh Super Admin, dan setiap penggunaan dicatat di log aplikasi.
 </p>
 </div>

 <x-input
 wire:model.live.debounce.300ms="search"
 placeholder="Cari nama atau email..."
 icon="o-magnifying-glass"
 clearable
 />

 <div class="overflow-x-auto">
 <table class="table table-zebra w-full text-base-content/70">
 <thead>
 <tr class="text-base-content/50 border-b border-base-300">
 <th>Nama</th>
 <th>Email</th>
 <th>Status Akun</th>
 <th class="text-right">Aksi</th>
 </tr>
 </thead>
 <tbody>
 @forelse ($this->users as $u)
 <tr class="border-b border-base-300">
 <td class="font-semibold text-base-content py-3">{{ $u->name }}</td>
 <td>{{ $u->email }}</td>
 <td>
 <span class="badge badge-sm {{ match ($u->status) {
 UserStatus::Active =>'badge-success',
 UserStatus::Invited =>'badge-warning',
 UserStatus::Suspended =>'badge-error',
 } }}">
 {{ match ($u->status) {
 UserStatus::Active =>'Aktif',
 UserStatus::Invited =>'Menunggu Aktivasi',
 UserStatus::Suspended =>'Dinonaktifkan',
 } }}
 </span>
 </td>
 <td class="text-right">
 <x-button
 label="Login sebagai user ini"
 icon="o-arrow-right-on-rectangle"
 class="btn-ghost btn-xs text-warning"
 :disabled="! $u->isActive()"
 wire:click="confirm({{ $u->id }})"
 />
 </td>
 </tr>
 @empty
 <tr>
 <td colspan="4" class="text-center text-base-content/50 py-8">
 Tidak ada user yang cocok.
 </td>
 </tr>
 @endforelse
 </tbody>
 </table>
 </div>

 <div class="mt-4">
 <x-pagination-footer :paginator="$this->users" />
 </div>
 </div>

 <x-modal wire:model="showConfirmModal" title="Login sebagai user lain?" subtitle="Sesi Anda saat ini sebagai Super Admin akan digantikan oleh sesi user yang dipilih. Aksi ini dicatat di log aplikasi.">
 @if ($this->confirmTarget)
 <p class="text-sm text-base-content/70">
 Anda akan login sebagai <span class="font-semibold text-base-content">{{ $this->confirmTarget->name }}</span>
 ({{ $this->confirmTarget->email }}).
 </p>
 @endif
 <div class="flex justify-end gap-2 pt-4">
 <x-button label="Batal" wire:click="cancel" class="btn-ghost" />
 <x-button label="Ya, Login" wire:click="loginAs" class="btn-warning" />
 </div>
 </x-modal>
</div>
