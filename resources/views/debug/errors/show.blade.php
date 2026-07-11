<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    @php($title = 'Error '.$error->error_code)
    @include('partials.head')
</head>

<body class="min-h-screen bg-neutral-950 text-neutral-100 antialiased">
    <div class="mx-auto max-w-5xl space-y-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <a href="{{ route('dev.errors.index') }}" class="text-xs text-indigo-400 hover:underline">&larr; Kembali ke daftar</a>
                <h1 class="mt-1 font-mono text-xl font-bold">{{ $error->error_code }}</h1>
            </div>
            <span @class([
                'rounded-full px-3 py-1 text-xs font-semibold',
                'bg-red-950 text-red-300' => $error->severity === 'critical',
                'bg-orange-950 text-orange-300' => $error->severity === 'error',
                'bg-amber-950 text-amber-300' => $error->severity === 'warning',
                'bg-neutral-800 text-neutral-300' => $error->severity === 'info',
            ])>{{ strtoupper($error->severity) }}</span>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4 space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-neutral-500">Type</span><span>{{ $error->type }}</span></div>
                <div class="flex justify-between"><span class="text-neutral-500">Status</span><span>{{ $error->status ?? '-' }}</span></div>
                <div class="flex justify-between"><span class="text-neutral-500">Route</span><span>{{ $error->route_name ?? '-' }}</span></div>
                <div class="flex justify-between"><span class="text-neutral-500">Method</span><span>{{ $error->method ?? '-' }}</span></div>
                <div class="flex justify-between"><span class="text-neutral-500">File</span><span class="truncate">{{ $error->file ?? '-' }}</span></div>
                <div class="flex justify-between"><span class="text-neutral-500">Line</span><span>{{ $error->line ?? '-' }}</span></div>
                <div class="flex justify-between"><span class="text-neutral-500">Waktu</span><span>{{ $error->created_at->format('d M Y H:i:s') }}</span></div>
            </div>
            <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4 space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-neutral-500">User</span><span>{{ $error->user_name ?? '-' }}</span></div>
                <div class="flex justify-between"><span class="text-neutral-500">Email</span><span>{{ $error->user_email ?? '-' }}</span></div>
                <div class="text-neutral-500">URL</div>
                <div class="break-all text-neutral-300">{{ $error->url ?? '-' }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4">
            <p class="mb-2 text-xs text-neutral-500">Pesan</p>
            <p class="text-sm text-neutral-100">{{ $error->message }}</p>
        </div>

        @if ($error->stack_trace)
            <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4">
                <p class="mb-2 text-xs text-neutral-500">Stack Trace</p>
                <pre class="max-h-96 overflow-auto text-xs text-neutral-400 whitespace-pre-wrap">{{ $error->stack_trace }}</pre>
            </div>
        @endif

        @if ($error->context)
            <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-4">
                <p class="mb-2 text-xs text-neutral-500">Context</p>
                <pre class="max-h-96 overflow-auto text-xs text-neutral-400 whitespace-pre-wrap">{{ json_encode($error->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        @endif
    </div>
</body>

</html>
