<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    @php($title = 'Error Tracker')
    @include('partials.head')
</head>

<body class="min-h-screen bg-neutral-950 text-neutral-100 antialiased">
    <div class="mx-auto max-w-7xl space-y-6 p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold">Error Tracker</h1>
                <p class="text-sm text-neutral-400">Panel debug internal &mdash; log error backend & JavaScript.</p>
            </div>
            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('dev.errors.purge') }}" onsubmit="return confirm('Hapus semua log error?');">
                    @csrf
                    <button type="submit" class="rounded-lg border border-red-900 bg-red-950/50 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-950">
                        Purge Semua
                    </button>
                </form>
                <a href="{{ route('dev.logout') }}" class="rounded-lg border border-neutral-700 px-3 py-1.5 text-xs font-semibold text-neutral-300 hover:bg-neutral-900">
                    Logout
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-900 bg-emerald-950/50 px-4 py-2 text-sm text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4">
                <p class="text-xs text-neutral-500">Total</p>
                <p class="mt-1 text-2xl font-bold">{{ $stats['total'] }}</p>
            </div>
            <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4">
                <p class="text-xs text-neutral-500">Hari Ini</p>
                <p class="mt-1 text-2xl font-bold">{{ $stats['today'] }}</p>
            </div>
            <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4">
                <p class="text-xs text-neutral-500">Belum Resolved</p>
                <p class="mt-1 text-2xl font-bold text-amber-400">{{ $stats['unresolved'] }}</p>
            </div>
            <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4">
                <p class="text-xs text-neutral-500">Critical Unresolved</p>
                <p class="mt-1 text-2xl font-bold text-red-400">{{ $stats['critical_unresolved'] }}</p>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border border-neutral-800 bg-neutral-900 p-4">
            <div>
                <label class="mb-1 block text-xs text-neutral-500">Type</label>
                <select name="type" class="rounded-lg border border-neutral-700 bg-neutral-950 px-2 py-1.5 text-sm">
                    <option value="">Semua</option>
                    @foreach (['exception', 'http', 'javascript'] as $type)
                        <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-neutral-500">Status Code</label>
                <input type="number" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="mis. 500"
                    class="w-24 rounded-lg border border-neutral-700 bg-neutral-950 px-2 py-1.5 text-sm" />
            </div>
            <div>
                <label class="mb-1 block text-xs text-neutral-500">Severity</label>
                <select name="severity" class="rounded-lg border border-neutral-700 bg-neutral-950 px-2 py-1.5 text-sm">
                    <option value="">Semua</option>
                    @foreach (['critical', 'error', 'warning', 'info'] as $sev)
                        <option value="{{ $sev }}" @selected(($filters['severity'] ?? '') === $sev)>{{ $sev }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-neutral-500">Resolved</label>
                <select name="resolved" class="rounded-lg border border-neutral-700 bg-neutral-950 px-2 py-1.5 text-sm">
                    <option value="">Semua</option>
                    <option value="1" @selected(($filters['resolved'] ?? '') === '1')>Resolved</option>
                    <option value="0" @selected(($filters['resolved'] ?? '') === '0')>Belum</option>
                </select>
            </div>
            <div class="flex-1 min-w-48">
                <label class="mb-1 block text-xs text-neutral-500">Cari</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="kode, pesan, user, route..."
                    class="w-full rounded-lg border border-neutral-700 bg-neutral-950 px-2 py-1.5 text-sm" />
            </div>
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">
                Filter
            </button>
            <a href="{{ route('dev.errors.index') }}" class="rounded-lg border border-neutral-700 px-4 py-1.5 text-sm text-neutral-300 hover:bg-neutral-800">
                Reset
            </a>
        </form>

        <!-- Table -->
        <div class="overflow-x-auto rounded-xl border border-neutral-800">
            <table class="w-full text-sm">
                <thead class="bg-neutral-900 text-left text-xs text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Severity</th>
                        <th class="px-4 py-3">Pesan</th>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3 text-center">Resolved</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-800">
                    @forelse ($errors as $error)
                        <tr class="hover:bg-neutral-900/60">
                            <td class="px-4 py-3">
                                <a href="{{ route('dev.errors.show', $error->error_code) }}" class="font-mono text-indigo-400 hover:underline">
                                    {{ $error->error_code }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-neutral-400">{{ $error->type }}</td>
                            <td class="px-4 py-3 text-neutral-400">{{ $error->status ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs font-semibold',
                                    'bg-red-950 text-red-300' => $error->severity === 'critical',
                                    'bg-orange-950 text-orange-300' => $error->severity === 'error',
                                    'bg-amber-950 text-amber-300' => $error->severity === 'warning',
                                    'bg-neutral-800 text-neutral-300' => $error->severity === 'info',
                                ])>{{ $error->severity }}</span>
                            </td>
                            <td class="max-w-xs truncate px-4 py-3 text-neutral-300" title="{{ $error->message }}">{{ $error->message }}</td>
                            <td class="px-4 py-3 text-neutral-400">{{ $error->user_email ?? '-' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-500">{{ $error->created_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-center">
                                <button type="button"
                                    data-resolve-id="{{ $error->id }}"
                                    class="js-resolve-toggle rounded-full px-2 py-0.5 text-xs font-semibold {{ $error->resolved ? 'bg-emerald-950 text-emerald-300' : 'bg-neutral-800 text-neutral-400' }}">
                                    {{ $error->resolved ? 'Resolved' : 'Belum' }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('dev.errors.destroy', $error->id) }}" onsubmit="return confirm('Hapus log ini?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-red-400 hover:text-red-300">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-neutral-500">Belum ada log error.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $errors->links() }}
        </div>
    </div>

    <script>
        document.querySelectorAll('.js-resolve-toggle').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.resolveId;
                const response = await fetch(`/dev/errors/${id}/resolve`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                });
                const data = await response.json();
                btn.textContent = data.resolved ? 'Resolved' : 'Belum';
                btn.classList.toggle('bg-emerald-950', data.resolved);
                btn.classList.toggle('text-emerald-300', data.resolved);
                btn.classList.toggle('bg-neutral-800', !data.resolved);
                btn.classList.toggle('text-neutral-400', !data.resolved);
            });
        });
    </script>
</body>

</html>
