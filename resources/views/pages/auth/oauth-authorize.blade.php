{{-- Passport consent screen: a connected app (SSO, /tech/sso) asks to sign the user in. --}}
<x-layouts::auth :title="__('Izinkan akses')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="'Masuk ke '.$client->name"
            :description="'Aplikasi ini meminta izin untuk memakai akun SmartID Anda.'"
        />

        <div class="rounded-box border border-base-300 bg-base-200/40 p-4 text-sm">
            <p class="text-base-content/60">Masuk sebagai</p>
            <p class="font-semibold">{{ $user->name }}</p>
            <p class="text-base-content/60">{{ $user->email }}</p>
        </div>

        @if (count($scopes) > 0)
            <div class="text-sm">
                <p class="mb-2 font-medium">{{ $client->name }} akan dapat melihat:</p>
                <ul class="space-y-1.5" data-test="oauth-scopes">
                    @foreach ($scopes as $scope)
                        <li class="flex items-center gap-2">
                            <span class="size-1.5 rounded-full bg-primary"></span>
                            {{ $scope->description }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3">
            <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <x-button label="Tolak" type="submit" class="btn-ghost w-full" />
            </form>

            <form method="POST" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <x-button label="Izinkan" type="submit" class="btn-primary w-full" data-test="oauth-approve-button" />
            </form>
        </div>
    </div>
</x-layouts::auth>
