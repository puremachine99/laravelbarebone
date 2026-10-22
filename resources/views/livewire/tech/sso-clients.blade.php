@php
    $copy = fn (string $text) => 'navigator.clipboard.writeText('.\Illuminate\Support\Js::from($text).'); copied = true; setTimeout(() => copied = false, 1500)';
    $baseUrl = rtrim(config('app.url'), '/');
@endphp

<div class="space-y-4">
    <x-nakama.flash />

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-base-content">SSO / Connected Apps</h1>
            <p class="text-sm text-base-content/60 mt-0.5">
                Aplikasi internal yang boleh "Login with SmartID" (OAuth2 authorization code). Semua aksi tercatat di Audit.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <div role="tablist" class="tabs tabs-boxed w-fit">
                <button type="button" role="tab" wire:click="$set('tab', 'apps')" class="tab {{ $tab === 'apps' ? 'tab-active' : '' }}">Aplikasi</button>
                <button type="button" role="tab" wire:click="$set('tab', 'sessions')" class="tab {{ $tab === 'sessions' ? 'tab-active' : '' }}">Sesi aktif</button>
                <button type="button" role="tab" wire:click="$set('tab', 'audit')" class="tab {{ $tab === 'audit' ? 'tab-active' : '' }}">Audit</button>
            </div>
            <x-button label="Tambah aplikasi" icon="o-plus" wire:click="create" class="btn-primary btn-sm" />
        </div>
    </div>

    @if ($tab === 'apps')
        <div class="bg-base-100 border border-base-300 rounded-box overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Aplikasi</th>
                        <th>Client ID</th>
                        <th>Tipe</th>
                        <th>Akses</th>
                        <th class="text-right">Sesi aktif</th>
                        <th>Terakhir dipakai</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->clients as $client)
                        <tr wire:key="client-{{ $client->getKey() }}" @class(['opacity-60' => $client->revoked])>
                            <td>
                                <p class="font-semibold">{{ $client->name }}</p>
                                @if ($client->description)
                                    <p class="text-xs text-base-content/60">{{ $client->description }}</p>
                                @endif
                                <p class="text-xs text-base-content/50 font-mono">{{ implode(', ', $client->redirect_uris) }}</p>
                            </td>
                            <td class="font-mono text-xs" x-data="{ copied: false }">
                                {{ $client->getKey() }}
                                <button type="button" class="btn btn-ghost btn-xs" x-on:click="{{ $copy($client->getKey()) }}" title="Salin Client ID">
                                    <span x-show="!copied">Salin</span><span x-show="copied" x-cloak>✓</span>
                                </button>
                            </td>
                            <td>
                                <span class="badge badge-sm {{ $client->confidential() ? 'badge-info' : 'badge-ghost' }}">{{ $client->confidential() ? 'Confidential' : 'Public (PKCE)' }}</span>
                                @if ($client->skip_authorization)
                                    <span class="badge badge-sm badge-ghost">Tanpa consent</span>
                                @endif
                            </td>
                            <td class="text-xs">
                                @if (empty($client->allowed_roles) && empty($client->allowed_department_ids))
                                    Semua user aktif
                                @else
                                    @if (! empty($client->allowed_roles))
                                        <p>Role: {{ implode(', ', $client->allowed_roles) }}</p>
                                    @endif
                                    @if (! empty($client->allowed_department_ids))
                                        <p>Dept: {{ collect($client->allowed_department_ids)->map(fn ($id) => $this->departmentOptions[$id] ?? "#{$id}")->implode(', ') }}</p>
                                    @endif
                                @endif
                            </td>
                            <td class="text-right tabular-nums">{{ $client->active_sessions_count }}</td>
                            <td class="text-xs">{{ $client->last_used_at?->diffForHumans() ?? '-' }}</td>
                            <td>
                                <span class="badge badge-sm {{ $client->revoked ? 'badge-error' : 'badge-success' }}">{{ $client->revoked ? 'Nonaktif' : 'Aktif' }}</span>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <x-button label="Kredensial" wire:click="openGuide('{{ $client->getKey() }}')" class="btn-xs btn-primary btn-outline" />
                                <x-button label="Simulasi" icon="o-beaker" wire:click="openSimulation('{{ $client->getKey() }}')" class="btn-xs btn-ghost" />
                                <x-dropdown right>
                                    <x-slot:trigger>
                                        <x-button icon="o-ellipsis-vertical" class="btn-xs btn-ghost" />
                                    </x-slot:trigger>
                                    <x-menu-item title="Edit" icon="o-pencil" wire:click="edit('{{ $client->getKey() }}')" />
                                    <x-menu-item title="Cabut semua sesi" icon="o-arrow-right-start-on-rectangle"
                                        wire:click="revokeTokens('{{ $client->getKey() }}')"
                                        wire:confirm="Cabut semua sesi {{ $client->name }}? Semua user harus login ulang." />
                                    <x-menu-item :title="$client->revoked ? 'Aktifkan' : 'Nonaktifkan'" icon="o-power"
                                        wire:click="toggleEnabled('{{ $client->getKey() }}')"
                                        wire:confirm="{{ $client->revoked ? 'Aktifkan kembali '.$client->name.'?' : 'Nonaktifkan '.$client->name.'? Login baru ditolak dan semua sesi dicabut.' }}" />
                                    <x-menu-item title="Hapus" icon="o-trash" class="text-error"
                                        wire:click="delete('{{ $client->getKey() }}')"
                                        wire:confirm="Hapus {{ $client->name }} permanen? Tidak bisa dibatalkan." />
                                </x-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-base-content/60 py-8">
                                Belum ada aplikasi terhubung. Klik "Tambah aplikasi" untuk mendaftarkan aplikasi pertama.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($tab === 'sessions')
        <div class="bg-base-100 border border-base-300 rounded-box overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Aplikasi</th>
                        <th>Scope</th>
                        <th>Login</th>
                        <th>Kedaluwarsa</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->sessions as $session)
                        <tr wire:key="session-{{ $session->id }}">
                            <td>
                                <p class="font-medium">{{ $session->user?->name ?? '-' }}</p>
                                <p class="text-xs text-base-content/60">{{ $session->user?->email }}</p>
                            </td>
                            <td>{{ $session->client?->name ?? '-' }}</td>
                            <td class="text-xs">{{ implode(', ', $session->scopes ?? []) }}</td>
                            <td class="text-xs">{{ $session->created_at?->format('d M Y H:i') }}</td>
                            <td class="text-xs">{{ $session->expires_at?->diffForHumans() }}</td>
                            <td class="text-right">
                                @if ($session->client && $session->user_id)
                                    <x-button label="Cabut" wire:click="revokeTokens('{{ $session->client_id }}', {{ $session->user_id }})"
                                        wire:confirm="Cabut sesi {{ $session->user?->name }} di {{ $session->client->name }}?"
                                        class="btn-xs btn-error btn-outline" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-base-content/60 py-8">Tidak ada sesi aktif.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="bg-base-100 border border-base-300 rounded-box overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Aplikasi</th>
                        <th>Aksi</th>
                        <th>Oleh</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->audits as $audit)
                        <tr wire:key="audit-{{ $audit->id }}">
                            <td class="text-xs whitespace-nowrap">{{ $audit->created_at?->format('d M Y H:i:s') }}</td>
                            <td>{{ $audit->client_name }}</td>
                            <td>
                                <span @class(['badge badge-sm', 'badge-warning' => in_array($audit->action, ['secret_revealed', 'secret_regenerated'], true), 'badge-error' => in_array($audit->action, ['deleted', 'disabled', 'tokens_revoked', 'user_tokens_revoked'], true), 'badge-ghost' => ! in_array($audit->action, ['secret_revealed', 'secret_regenerated', 'deleted', 'disabled', 'tokens_revoked', 'user_tokens_revoked'], true)])>{{ $audit->label() }}</span>
                            </td>
                            <td>{{ $audit->user?->name ?? '-' }}</td>
                            <td class="font-mono text-xs">{{ $audit->ip_address }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-base-content/60 py-8">Belum ada aktivitas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- Create / edit --}}
    <x-modal wire:model="showForm" :title="$editingId ? 'Edit aplikasi' : 'Tambah aplikasi'"
        subtitle="Cukup isi nama dan URL aplikasi. Pengaturan lain sudah diisi dengan nilai yang aman."
        box-class="w-11/12 max-w-2xl">
        <form wire:submit="save" class="space-y-5">
            <x-input label="Nama aplikasi" wire:model="name" placeholder="mis. Nakama Procurement" required
                hint="Ditampilkan ke user saat login: &quot;Masuk ke Nakama Procurement&quot;." />

            <div>
                <x-input label="URL aplikasi" wire:model="appUrl" placeholder="https://procurement.smartid.co.id"
                    hint="Alamat aplikasi client (tanpa path). Untuk development boleh http://localhost:8000." />
                <p class="text-xs text-base-content/60 mt-1.5" x-show="! $wire.redirectUris">
                    Callback otomatis:
                    <code class="font-mono" x-text="($wire.appUrl || 'https://aplikasi-anda.co.id').trim().replace(/\/+$/, '') + '{{ \App\Livewire\Tech\SsoClients::CALLBACK_PATH }}'"></code>
                </p>
            </div>

            <div class="space-y-2">
                <label class="flex items-center gap-3 text-sm cursor-pointer">
                    <input type="checkbox" wire:model.live="restrictAccess" class="toggle toggle-sm toggle-primary" />
                    <span>
                        <span class="font-medium">Batasi siapa yang boleh login</span>
                        <span class="block text-xs text-base-content/60">Mati = semua karyawan dengan akun aktif boleh login.</span>
                    </span>
                </label>

                @if ($restrictAccess)
                    <div class="grid sm:grid-cols-2 gap-4 rounded-box border border-base-300 p-3">
                        <div>
                            <p class="text-sm font-medium mb-1.5">Role</p>
                            <div class="max-h-40 overflow-y-auto space-y-1">
                                @foreach ($this->roleOptions as $role)
                                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                                        <input type="checkbox" wire:model="allowedRoles" value="{{ $role }}" class="checkbox checkbox-sm checkbox-primary" /> {{ $role }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium mb-1.5">Departemen</p>
                            <div class="max-h-40 overflow-y-auto space-y-1">
                                @foreach ($this->departmentOptions as $id => $department)
                                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                                        <input type="checkbox" wire:model="allowedDepartmentIds" value="{{ $id }}" class="checkbox checkbox-sm checkbox-primary" /> {{ $department }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <p class="sm:col-span-2 text-xs text-base-content/60">User cukup memenuhi salah satu: punya role yang dicentang <em>atau</em> ada di departemen yang dicentang.</p>
                    </div>
                @endif
                @error('allowedRoles') <p class="text-error text-xs">{{ $message }}</p> @enderror
            </div>

            {{-- Advanced: closed by default; opens by itself when editing an app that already uses it --}}
            <div x-data="{ advanced: @js($redirectUris !== '' || $clientType === 'public' || $skipAuthorization) }" class="rounded-box border border-base-300">
                <button type="button" x-on:click="advanced = ! advanced" class="flex w-full items-center justify-between px-3 py-2 text-sm font-medium">
                    <span>
                        Pengaturan lanjutan
                        @if ($errors->hasAny(['redirectUris', 'description', 'scopes', 'clientType']))
                            <span class="badge badge-error badge-xs ml-1">periksa</span>
                        @endif
                    </span>
                    <span class="text-xs text-base-content/60" x-text="advanced ? 'Sembunyikan' : 'Tampilkan'"></span>
                </button>

                <div x-show="advanced" x-cloak class="space-y-4 border-t border-base-300 p-3">
                    <x-input label="Deskripsi (opsional)" wire:model="description" placeholder="Dipakai tim ..." />

                    <x-textarea label="Redirect URI manual (opsional, satu per baris)" wire:model.live.debounce.400ms="redirectUris" rows="2"
                        placeholder="https://procurement.smartid.co.id/custom/callback"
                        hint="Isi hanya kalau callback aplikasi tidak memakai /auth/smartid/callback. Kalau diisi, callback otomatis di atas tidak dipakai." />

                    @unless ($editingId)
                        <div>
                            <p class="text-sm font-medium mb-1.5">Tipe aplikasi</p>
                            <div class="grid sm:grid-cols-2 gap-2">
                                <label class="flex gap-3 items-start border border-base-300 rounded-box p-3 cursor-pointer has-[:checked]:border-primary">
                                    <input type="radio" wire:model="clientType" value="confidential" class="radio radio-primary radio-sm mt-0.5" />
                                    <span>
                                        <span class="font-medium text-sm">Punya backend (default)</span>
                                        <span class="block text-xs text-base-content/60">Laravel, Filament, dsb. Memakai client secret.</span>
                                    </span>
                                </label>
                                <label class="flex gap-3 items-start border border-base-300 rounded-box p-3 cursor-pointer has-[:checked]:border-primary">
                                    <input type="radio" wire:model="clientType" value="public" class="radio radio-primary radio-sm mt-0.5" />
                                    <span>
                                        <span class="font-medium text-sm">SPA / mobile</span>
                                        <span class="block text-xs text-base-content/60">Tanpa secret, wajib PKCE.</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    @endunless

                    <div>
                        <p class="text-sm font-medium mb-1.5">Data user yang boleh diminta</p>
                        <div class="grid sm:grid-cols-2 gap-2">
                            @foreach (\App\Models\OAuthClient::SCOPES as $scope => $label)
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="checkbox" wire:model="scopes" value="{{ $scope }}" class="checkbox checkbox-sm checkbox-primary" @disabled($scope === 'profile') />
                                    <span>{{ $label }} <span class="font-mono text-xs text-base-content/50">({{ $scope }})</span></span>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-base-content/60 mt-1">Panduan pemasangan mencocokkan user lewat email, jadi biarkan <span class="font-mono">email</span> tercentang.</p>
                    </div>

                    <label class="flex items-center gap-3 text-sm cursor-pointer">
                        <input type="checkbox" wire:model="skipAuthorization" class="toggle toggle-sm toggle-primary" />
                        <span>
                            <span class="font-medium">Lewati layar "Izinkan akses?"</span>
                            <span class="block text-xs text-base-content/60">Hanya untuk aplikasi internal yang dipercaya.</span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
                <x-button label="Batal" wire:click="$set('showForm', false)" class="btn-ghost" />
                <x-button :label="$editingId ? 'Simpan' : 'Daftarkan & lihat cara pasang'" type="submit" class="btn-primary" spinner="save" />
            </div>
        </form>
    </x-modal>

    {{-- Credentials & step-by-step setup for the client app --}}
    <x-modal wire:model="showGuide" :title="'Cara pasang — '.($this->guideClient?->name ?? '')" box-class="w-11/12 max-w-4xl">
        @if ($client = $this->guideClient)
            @php
                $secretShown = $secretClientId === $client->getKey() && $revealedSecret !== null;
            @endphp

            <div class="space-y-5 text-sm">
                <div class="grid gap-3 rounded-box bg-base-200/50 p-3">
                    <div x-data="{ copied: false }">
                        <p class="text-xs text-base-content/60">Client ID</p>
                        <div class="flex items-center gap-2">
                            <code class="font-mono bg-base-100 rounded px-2 py-1 grow break-all">{{ $client->getKey() }}</code>
                            <button type="button" class="btn btn-xs" x-on:click="{{ $copy($client->getKey()) }}"><span x-show="!copied">Salin</span><span x-show="copied" x-cloak>✓</span></button>
                        </div>
                    </div>

                    @if ($client->confidential())
                        <div x-data="{ copied: false }">
                            <p class="text-xs text-base-content/60">Client secret</p>
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($secretShown)
                                    <code class="font-mono bg-base-100 rounded px-2 py-1 grow break-all" data-test="revealed-secret">{{ $revealedSecret }}</code>
                                    <button type="button" class="btn btn-xs" x-on:click="{{ $copy($revealedSecret) }}"><span x-show="!copied">Salin</span><span x-show="copied" x-cloak>✓</span></button>
                                    <x-button label="Sembunyikan" wire:click="hideSecret" class="btn-xs btn-ghost" />
                                @else
                                    <code class="font-mono bg-base-100 rounded px-2 py-1 grow">••••••••••••••••••••••••••••••••••••••••</code>
                                    <x-button label="Tampilkan secret" icon="o-eye" wire:click="revealSecret('{{ $client->getKey() }}')" class="btn-xs btn-warning" spinner="revealSecret" />
                                @endif
                                <x-button label="Generate ulang" icon="o-arrow-path" wire:click="regenerateSecret('{{ $client->getKey() }}')"
                                    wire:confirm="Generate secret baru untuk {{ $client->name }}? Secret lama langsung tidak berlaku sampai .env aplikasi tersebut di-update."
                                    class="btn-xs btn-ghost" />
                            </div>
                            @error('secret') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-base-content/50 mt-1">Disimpan terenkripsi. Setiap kali ditampilkan tercatat di Audit.</p>
                        </div>
                    @endif
                </div>

                @if ($client->confidential())
                    <p class="text-base-content/70">
                        Salin ke <strong>aplikasi client</strong> (Laravel 11+) sesuai urutan. Semua nilai sudah terisi untuk aplikasi ini.
                    </p>

                    @include('partials.sso-laravel-guide', [
                        'host' => $baseUrl,
                        'clientId' => $client->getKey(),
                        'clientSecret' => $secretShown ? $revealedSecret : null,
                        'revealClientId' => $client->getKey(),
                        'redirect' => $client->redirect_uris[0] ?? '',
                        'scopes' => implode(' ', $client->scopes ?? ['profile', 'email']),
                    ])
                @else
                    <p class="text-base-content/70">
                        Aplikasi SPA / mobile: tidak memakai secret. Gunakan library OAuth2 dengan PKCE
                        (<code>code_challenge_method=S256</code>) ke <code>{{ $baseUrl }}/oauth/authorize</code> dan
                        <code>{{ $baseUrl }}/oauth/token</code>, lalu ambil profil di <code>{{ $baseUrl }}/api/me</code>.
                    </p>
                @endif

                <p class="text-xs text-base-content/60">
                    Ada kendala? Lihat <a href="{{ route('tech.documentation') }}#sso-troubleshooting" class="link" target="_blank">Troubleshooting SSO</a>
                    di API Documentation.
                </p>
            </div>
        @endif
    </x-modal>

    {{-- Simulation: what the app would receive, without the app running --}}
    <x-modal wire:model="showSimulation" :title="'Simulasi — '.($this->simulationClient?->name ?? '')" box-class="w-11/12 max-w-3xl">
        @if ($client = $this->simulationClient)
            <div class="space-y-6 text-sm">
                <section class="space-y-3">
                    <h3 class="font-semibold">Login satu user <span class="font-mono text-xs text-base-content/60">GET /api/me</span></h3>

                    <div class="relative">
                        <input type="search" wire:model.live.debounce.300ms="simulationSearch" placeholder="Cari nama atau email karyawan..."
                            class="input input-bordered input-sm w-full" aria-label="Cari karyawan" />
                        @if ($this->simulationUserOptions->isNotEmpty())
                            <ul class="menu menu-sm absolute z-10 mt-1 w-full rounded-box border border-base-300 bg-base-100 shadow">
                                @foreach ($this->simulationUserOptions as $option)
                                    <li wire:key="sim-user-{{ $option->id }}">
                                        <button type="button" wire:click="simulateUser({{ $option->id }})">
                                            <span class="grow text-left">{{ $option->name }} <span class="text-base-content/50">{{ $option->email }}</span></span>
                                            @unless ($option->isActive())
                                                <span class="badge badge-ghost badge-xs">{{ $option->status->value }}</span>
                                            @endunless
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    @if ($simulation = $this->simulation)
                        <div class="space-y-2" data-test="simulation-result">
                            <div @class(['alert py-2', 'alert-success' => $simulation['allowed'], 'alert-error' => ! $simulation['allowed']])>
                                <span class="text-white">
                                    <strong>{{ $simulation['allowed'] ? '✓ Boleh login' : '✗ Ditolak' }}</strong> — {{ $simulation['user']->name }}.
                                    {{ $simulation['reason'] }}
                                </span>
                            </div>
                            <p class="text-xs text-base-content/60">Response yang diterima aplikasi: <span class="font-mono">HTTP {{ $simulation['status'] }}</span></p>
                            <pre class="max-h-80 overflow-auto rounded-box bg-code-bg p-3 text-xs text-code-fg"><code>{{ $simulation['json'] }}</code></pre>
                            @if ($simulation['allowed'])
                                <p class="text-xs text-base-content/60">Aturan jabatan milik aplikasi sendiri (<span class="font-mono">smartIdAllows()</span>) dinilai dari data ini.</p>
                            @endif
                        </div>
                    @endif
                </section>

                <section class="space-y-3">
                    <h3 class="font-semibold">Sync <span class="font-mono text-xs text-base-content/60">php artisan smartid:sync</span></h3>

                    @if (! $client->confidential())
                        <p class="text-base-content/60">Aplikasi public (PKCE) tidak bisa memakai sync.</p>
                    @elseif ($preview = $this->syncPreview)
                        <div class="space-y-2" data-test="sync-preview">
                            <p><strong class="tabular-nums">{{ $preview['total'] }}</strong> user akan tersinkron. User lokal lain yang pernah login akan dikunci.</p>
                            @if ($preview['total'] > 0)
                                <p class="text-xs text-base-content/60">Contoh {{ min(5, $preview['total']) }} data pertama:</p>
                                <pre class="max-h-80 overflow-auto rounded-box bg-code-bg p-3 text-xs text-code-fg"><code>{{ $preview['json'] }}</code></pre>
                            @endif
                        </div>
                    @else
                        <x-button label="Jalankan simulasi sync" icon="o-arrow-path" wire:click="runSyncSimulation" class="btn-sm" spinner="runSyncSimulation" />
                    @endif
                </section>
            </div>
        @endif
    </x-modal>
</div>
