{{-- Shown instead of the consent screen when the signed-in user may not use this connected app. --}}
<x-layouts::auth :title="__('Akses ditolak')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="'Tidak bisa masuk ke '.$client->name"
            :description="'Akun Anda tidak memiliki akses ke aplikasi ini. Hubungi admin bila Anda seharusnya punya akses.'"
        />

        <div class="rounded-box border border-base-300 bg-base-200/40 p-4 text-sm">
            <p class="text-base-content/60">Masuk sebagai</p>
            <p class="font-semibold">{{ $user->name }}</p>
            <p class="text-base-content/60">{{ $user->email }}</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-button label="Ganti akun" type="submit" class="btn-ghost w-full" />
            </form>
            <x-button label="Ke dashboard" link="{{ route('dashboard') }}" class="btn-primary w-full" />
        </div>
    </div>
</x-layouts::auth>
