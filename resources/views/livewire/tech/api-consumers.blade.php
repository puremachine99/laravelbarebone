@php
    $copy = fn (string $text) => 'navigator.clipboard.writeText('.\Illuminate\Support\Js::from($text).'); copied = true; setTimeout(() => copied = false, 1500)';
    $baseUrl = rtrim(config('app.url'), '/');
    $header = \App\Models\ApiConsumer::HEADER;
@endphp

<div class="space-y-4">
    @if (session()->has('success'))
        <div class="alert alert-success shadow-sm">
            <x-icon name="o-check-circle" class="w-5 h-5 text-white" />
            <span class="text-white">{{ session('success') }}</span>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-base-content">API Keys</h1>
            <p class="text-sm text-base-content/60 mt-0.5">
                Token per website/aplikasi yang memakai API publik OPD &amp; wilayah (<code class="font-mono">/api/opds</code>, <code class="font-mono">/api/regencies</code>, ...).
                Token hanya ditampilkan sekali saat dibuat.
                <a href="{{ route('tech.documentation') }}#api-key" class="link link-primary" wire:navigate>Dokumentasi</a>
            </p>
        </div>

        <x-button label="Tambah consumer" icon="o-plus" wire:click="create" class="btn-primary btn-sm" />
    </div>

    <div class="bg-base-100 border border-base-300 rounded-box overflow-x-auto">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Consumer</th>
                    <th>Website diizinkan</th>
                    <th>Token</th>
                    <th class="text-right">Limit / menit</th>
                    <th>Terakhir dipakai</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->consumers as $consumer)
                    <tr wire:key="consumer-{{ $consumer->id }}" @class(['opacity-60' => $consumer->isRevoked()])>
                        <td>
                            <p class="font-semibold">{{ $consumer->name }}</p>
                            @if ($consumer->contact_name || $consumer->contact_email)
                                <p class="text-xs text-base-content/60">{{ collect([$consumer->contact_name, $consumer->contact_email])->filter()->implode(' · ') }}</p>
                            @endif
                            @if ($consumer->notes)
                                <p class="text-xs text-base-content/50">{{ $consumer->notes }}</p>
                            @endif
                        </td>
                        <td class="text-xs">
                            @foreach ($consumer->allowed_origins ?? [] as $origin)
                                <span class="badge badge-sm badge-ghost font-mono">{{ $origin }}</span>
                            @endforeach
                            @if ($consumer->allow_server_requests)
                                <span class="badge badge-sm badge-info">+ server</span>
                            @endif
                        </td>
                        <td class="font-mono text-xs">{{ $consumer->token_prefix }}…</td>
                        <td class="text-right">{{ number_format($consumer->rate_limit_per_minute) }}</td>
                        <td class="text-xs">
                            @if ($consumer->last_used_at)
                                <span title="{{ $consumer->last_used_at }}">{{ $consumer->last_used_at->diffForHumans() }}</span>
                                <p class="text-base-content/50 font-mono">{{ $consumer->last_used_origin }}</p>
                            @else
                                <span class="text-base-content/50">Belum pernah</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-sm {{ $consumer->isRevoked() ? 'badge-error' : 'badge-success' }}">{{ $consumer->isRevoked() ? 'Dicabut' : 'Aktif' }}</span>
                        </td>
                        {{-- Inline actions: a dropdown inside the overflow-x-auto table gets clipped. --}}
                        <td>
                            <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                                <x-button icon="o-pencil" wire:click="edit({{ $consumer->id }})"
                                    class="btn-xs btn-ghost" tooltip-left="Edit" aria-label="Edit {{ $consumer->name }}" />
                                <x-button icon="o-arrow-path" wire:click="regenerate({{ $consumer->id }})"
                                    wire:confirm="Generate token baru untuk {{ $consumer->name }}? Token lama langsung tidak berlaku."
                                    class="btn-xs btn-ghost" tooltip-left="Generate token baru" aria-label="Generate token baru {{ $consumer->name }}" />
                                <x-button icon="o-power" wire:click="toggleRevoked({{ $consumer->id }})"
                                    wire:confirm="{{ $consumer->isRevoked() ? 'Aktifkan kembali '.$consumer->name.'?' : 'Cabut API key '.$consumer->name.'? Request berikutnya langsung ditolak.' }}"
                                    :class="'btn-xs btn-ghost '.($consumer->isRevoked() ? 'text-success' : 'text-warning')"
                                    :tooltip-left="$consumer->isRevoked() ? 'Aktifkan' : 'Cabut'" aria-label="{{ $consumer->isRevoked() ? 'Aktifkan' : 'Cabut' }} {{ $consumer->name }}" />
                                <x-button icon="o-trash" wire:click="delete({{ $consumer->id }})"
                                    wire:confirm="Hapus {{ $consumer->name }} permanen? Tidak bisa dibatalkan."
                                    class="btn-xs btn-ghost text-error" tooltip-left="Hapus" aria-label="Hapus {{ $consumer->name }}" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-base-content/50 py-8">Belum ada consumer. Klik "Tambah consumer" untuk menerbitkan API key pertama.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Edit consumer' : 'Tambah consumer'"
        subtitle="Siapa yang memakai API dan dari website mana." box-class="w-11/12 max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <x-input label="Nama consumer" wire:model="name" placeholder="mis. Portal Mitra Dinkes Jatim" required />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="PIC (opsional)" wire:model="contactName" placeholder="Nama penanggung jawab" />
                <x-input label="Email PIC (opsional)" wire:model="contactEmail" type="email" placeholder="pic@contoh.go.id" />
            </div>

            <x-textarea label="Website yang boleh memanggil API (satu per baris)" wire:model="allowedOrigins" rows="3"
                placeholder="portal.contoh.go.id&#10;*.contoh.go.id&#10;localhost:3000"
                hint="Dicocokkan dengan header Origin/Referer dari browser. Boleh tempel URL lengkap; yang dipakai hanya host-nya. *.domain untuk semua subdomain." />

            <label class="flex items-start gap-3 text-sm cursor-pointer">
                <input type="checkbox" wire:model="allowServerRequests" class="toggle toggle-sm toggle-primary mt-0.5" />
                <span>
                    Izinkan request dari server (tanpa Origin/Referer)
                    <span class="block text-xs text-base-content/60">Untuk backend yang memanggil API langsung (PHP, Node, cron). Biarkan mati kalau token hanya dipakai di browser.</span>
                </span>
            </label>

            <x-input label="Limit request per menit" wire:model="rateLimitPerMinute" type="number" min="1" max="2000"
                hint="Dihitung per consumer. 120 cukup untuk dropdown bertingkat biasa." />

            <x-textarea label="Catatan (opsional)" wire:model="notes" rows="2" placeholder="Keperluan, nomor surat/perjanjian, dll." />

            <div class="flex justify-end gap-2">
                <x-button label="Batal" wire:click="$set('showForm', false)" class="btn-ghost" />
                <x-button :label="$editingId ? 'Simpan' : 'Terbitkan API key'" type="submit" class="btn-primary" spinner="save" />
            </div>
        </form>
    </x-modal>

    <x-modal wire:model="showToken" :title="'API key — '.($issuedFor ?? '')"
        subtitle="Salin sekarang. Token ini tidak bisa ditampilkan lagi; kalau hilang, generate token baru." box-class="w-11/12 max-w-2xl" persistent>
        @if ($issuedToken)
            <div class="space-y-4" x-data="{ copied: false }">
                <div class="flex items-center gap-2">
                    <code class="font-mono text-sm bg-base-200 rounded px-3 py-2 grow break-all">{{ $issuedToken }}</code>
                    <x-button icon="o-clipboard" class="btn-sm" x-on:click="{{ $copy($issuedToken) }}" />
                    <span x-show="copied" x-cloak class="text-xs text-success">Tersalin</span>
                </div>

                <div>
                    <p class="text-sm font-semibold mb-1">Contoh pemakaian</p>
                    <pre class="text-xs bg-base-200 rounded p-3 overflow-x-auto"><code>const res = await fetch('{{ $baseUrl }}/api/provinces', {
    headers: { '{{ $header }}': '{{ $issuedToken }}', Accept: 'application/json' },
});</code></pre>
                </div>

                <div class="flex justify-end">
                    <x-button label="Sudah saya simpan" wire:click="hideToken" class="btn-primary" />
                </div>
            </div>
        @endif
    </x-modal>
</div>
