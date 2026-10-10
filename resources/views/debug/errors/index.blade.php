@php
    $severityBadge = fn (string $severity) => match ($severity) {
        'critical' => 'badge-error',
        'error' => 'badge-warning',
        'warning' => 'badge-info',
        default => 'badge-ghost',
    };
@endphp

<x-layouts::app.sidebar title="Sentry">
    <div class="space-y-4">

        {{-- Page Header --}}
        <div class="card bg-base-100 border border-base-300 shadow-sm">
            <div class="card-body p-5 flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-base-content">Sentry</h1>
                    <p class="text-sm text-base-content/60 mt-0.5">Log error backend &amp; JavaScript aplikasi Nakama, dan
                        Force Login untuk dukungan/debug. Hanya Super Admin.</p>
                </div>
                @if ($tab === 'errors')
                    <form method="POST" action="{{ route('dev.errors.purge') }}" onsubmit="return confirm('Hapus semua log error?');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-error btn-outline">Purge Semua</button>
                    </form>
                @endif
            </div>
        </div>

        <div role="tablist" class="tabs tabs-box w-fit">
            <a role="tab" href="{{ route('dev.errors.index') }}" wire:navigate
                @class(['tab', 'tab-active' => $tab === 'errors'])>Error Log</a>
            <a role="tab" href="{{ route('dev.errors.index', ['tab' => 'force-login']) }}" wire:navigate
                @class(['tab', 'tab-active' => $tab === 'force-login'])>Force Login</a>
        </div>

        @if ($tab === 'force-login')
            <livewire:dev.force-login />
        @else

        @if (session('status'))
            <div class="alert alert-success text-sm">{{ session('status') }}</div>
        @endif

        {{-- Stats --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            @foreach ([
                ['label' => 'Total', 'value' => $stats['total'], 'class' => 'text-base-content'],
                ['label' => 'Hari Ini', 'value' => $stats['today'], 'class' => 'text-base-content'],
                ['label' => 'Belum Resolved', 'value' => $stats['unresolved'], 'class' => 'text-warning'],
                ['label' => 'Critical Unresolved', 'value' => $stats['critical_unresolved'], 'class' => 'text-error'],
            ] as $stat)
                <div class="card bg-base-100 border border-base-300 shadow-sm">
                    <div class="card-body p-4 gap-1">
                        <p class="text-xs text-base-content/60">{{ $stat['label'] }}</p>
                        <p class="text-2xl font-bold {{ $stat['class'] }}">{{ $stat['value'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Filters + table --}}
        <div class="card bg-base-100 border border-base-300 shadow-sm">
            <div class="card-body p-5 gap-4">
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <label class="form-control">
                        <span class="label-text text-xs mb-1">Type</span>
                        <select name="type" class="select select-sm select-bordered">
                            <option value="">Semua</option>
                            @foreach (['exception', 'http', 'javascript'] as $type)
                                <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-control">
                        <span class="label-text text-xs mb-1">Status Code</span>
                        <input type="number" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="mis. 500"
                            class="input input-sm input-bordered w-28" />
                    </label>
                    <label class="form-control">
                        <span class="label-text text-xs mb-1">Severity</span>
                        <select name="severity" class="select select-sm select-bordered">
                            <option value="">Semua</option>
                            @foreach (['critical', 'error', 'warning', 'info'] as $severity)
                                <option value="{{ $severity }}" @selected(($filters['severity'] ?? '') === $severity)>{{ $severity }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-control">
                        <span class="label-text text-xs mb-1">Resolved</span>
                        <select name="resolved" class="select select-sm select-bordered">
                            <option value="">Semua</option>
                            <option value="1" @selected(($filters['resolved'] ?? '') === '1')>Resolved</option>
                            <option value="0" @selected(($filters['resolved'] ?? '') === '0')>Belum</option>
                        </select>
                    </label>
                    <label class="form-control flex-1 min-w-48">
                        <span class="label-text text-xs mb-1">Cari</span>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                            placeholder="kode, pesan, user, route..." class="input input-sm input-bordered w-full" />
                    </label>
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    <a href="{{ route('dev.errors.index') }}" wire:navigate class="btn btn-sm btn-ghost">Reset</a>
                </form>

                <div class="overflow-x-auto">
                    <table class="table table-sm w-full">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Severity</th>
                                <th>Pesan</th>
                                <th>User</th>
                                <th>Waktu</th>
                                <th class="text-center">Resolved</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($errors as $error)
                                <tr class="hover">
                                    <td>
                                        <a href="{{ route('dev.errors.show', $error->error_code) }}" wire:navigate
                                            class="link link-primary font-mono">{{ $error->error_code }}</a>
                                    </td>
                                    <td class="text-base-content/70">{{ $error->type }}</td>
                                    <td class="text-base-content/70">{{ $error->status ?? '-' }}</td>
                                    <td><span class="badge badge-sm {{ $severityBadge($error->severity) }}">{{ $error->severity }}</span></td>
                                    <td class="max-w-xs truncate" title="{{ $error->message }}">{{ $error->message }}</td>
                                    <td class="text-base-content/70">{{ $error->user_email ?? '-' }}</td>
                                    <td class="whitespace-nowrap text-base-content/60">{{ $error->created_at->format('d M Y H:i') }}</td>
                                    <td class="text-center">
                                        <button type="button"
                                            x-data="{ resolved: @js((bool) $error->resolved) }"
                                            x-on:click="resolved = (await (await fetch(@js(route('dev.errors.resolve', $error->id)), {
                                                method: 'POST',
                                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                                            })).json()).resolved"
                                            :class="resolved ? 'badge-success' : 'badge-ghost'"
                                            class="badge badge-sm cursor-pointer {{ $error->resolved ? 'badge-success' : 'badge-ghost' }}"
                                            x-text="resolved ? 'Resolved' : 'Belum'">{{ $error->resolved ? 'Resolved' : 'Belum' }}</button>
                                    </td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('dev.errors.destroy', $error->id) }}"
                                            onsubmit="return confirm('Hapus log ini?');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-ghost text-error">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="py-10 text-center text-base-content/50">Belum ada log error.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <form method="GET" class="flex items-center gap-2 text-xs text-base-content/60">
                        @foreach (request()->except(['per_page', 'page']) as $key => $value)
                            @if (is_scalar($value))
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <span>Tampilkan</span>
                        <select name="per_page" onchange="this.form.submit()" class="select select-xs select-bordered">
                            @foreach (\App\Support\PerPage::OPTIONS as $option)
                                <option value="{{ $option }}" @selected($errors->perPage() === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        <span>data per halaman</span>
                    </form>
                    <div>{{ $errors->links() }}</div>
                </div>
            </div>
        </div>
        @endif
    </div>
</x-layouts::app.sidebar>
