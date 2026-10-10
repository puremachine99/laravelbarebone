@php
    $severityBadge = match ($error->severity) {
        'critical' => 'badge-error',
        'error' => 'badge-warning',
        'warning' => 'badge-info',
        default => 'badge-ghost',
    };

    $context = $error->context ?? [];
    $ip = $context['ip'] ?? null;
    $userAgent = $context['user_agent'] ?? null;
    $triggeredBy = $error->exception_class ? class_basename($error->exception_class) : $error->type;

    // Everything an AI assistant needs to debug this error, as one plain-text block.
    $aiReport = collect([
        "Tolong bantu debug error berikut di aplikasi Laravel (Nakama).",
        '',
        "Kode: {$error->error_code}",
        "Pesan: {$error->message}",
        'Type: '.$error->type.($error->exception_class ? " ({$error->exception_class})" : ''),
        $error->status ? "HTTP status: {$error->status}" : null,
        $error->file ? "File: {$error->file}".($error->line ? ":{$error->line}" : '') : null,
        $error->url ? 'URL: '.($error->method ? "{$error->method} " : '').$error->url : null,
        $error->route_name ? "Route: {$error->route_name}" : null,
        "Waktu: {$error->created_at->format('Y-m-d H:i:s')}",
        $error->stack_trace ? "\nStack trace:\n{$error->stack_trace}" : null,
        $context ? "\nContext:\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null,
    ])->filter(fn ($line) => $line !== null)->implode("\n");
@endphp

<x-layouts::app.sidebar :title="'Error '.$error->error_code">
    <div class="space-y-4" x-data="{
        copied: null,
        resolved: @js((bool) $error->resolved),
        async copy(key, text) {
            await navigator.clipboard.writeText(text);
            this.copied = key;
            setTimeout(() => this.copied = null, 1500);
        },
        async toggleResolved() {
            const response = await fetch(@js(route('dev.errors.resolve', $error->id)), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
            });
            this.resolved = (await response.json()).resolved;
        },
    }">

        {{-- Page Header --}}
        <div class="card bg-base-100 border border-base-300 shadow-sm">
            <div class="card-body p-5 gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 space-y-1.5">
                    <a href="{{ route('dev.errors.index') }}" wire:navigate class="link link-primary text-xs">&larr; Kembali ke daftar</a>
                    <h1 class="flex items-center gap-2 font-mono text-xl font-bold text-base-content">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5 shrink-0 text-error" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        {{ $error->error_code }}
                    </h1>
                    <p class="text-base-content/80 wrap-break-word">{{ $error->message }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        <span class="badge badge-sm {{ $severityBadge }}">{{ $error->severity }}</span>
                        @if ($error->status)
                            <span class="badge badge-sm badge-error badge-outline">HTTP {{ $error->status }}</span>
                        @endif
                        <span class="badge badge-sm badge-ghost">{{ $error->type }}</span>
                        <span class="badge badge-sm badge-success" x-show="resolved" @style(['display: none' => ! $error->resolved])>resolved</span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 shrink-0">
                    <button type="button" class="btn btn-sm btn-secondary" x-on:click="copy('ai', @js($aiReport))">
                        <span x-text="copied === 'ai' ? 'Tersalin!' : 'Copy for AI'">Copy for AI</span>
                    </button>
                    <button type="button" class="btn btn-sm" :class="resolved ? 'btn-ghost border-base-300' : 'btn-success'"
                        x-on:click="toggleResolved()">
                        <span x-text="resolved ? 'Batalkan Resolved' : 'Tandai Resolved'">{{ $error->resolved ? 'Batalkan Resolved' : 'Tandai Resolved' }}</span>
                    </button>
                    <form method="POST" action="{{ route('dev.errors.destroy', $error->id) }}" onsubmit="return confirm('Hapus log ini?');">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="to_index" value="1">
                        <button type="submit" class="btn btn-sm btn-error btn-outline">Hapus</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Code for the user to report --}}
        <div class="card bg-base-100 border border-base-300 shadow-sm">
            <div class="card-body p-5 gap-3">
                <p class="text-xs font-semibold text-base-content/60 uppercase tracking-wider">Kode untuk dilaporkan user</p>
                <p class="text-sm text-base-content/70">User cukup kirim kode ini ke developer:</p>
                <div class="flex items-center justify-between gap-3 rounded-lg border border-base-300 bg-base-200 px-4 py-3">
                    <span class="font-mono text-lg tracking-wider">{{ $error->error_code }}</span>
                    <button type="button" class="btn btn-xs btn-primary" x-on:click="copy('code', @js($error->error_code))"
                        x-text="copied === 'code' ? 'Tersalin!' : 'Copy'">Copy</button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            {{-- Info error --}}
            <div class="card bg-base-100 border border-base-300 shadow-sm">
                <div class="card-body p-5 gap-4">
                    <p class="text-xs font-semibold text-base-content/60 uppercase tracking-wider">Info Error</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <div>
                            <p class="text-xs text-base-content/50 uppercase">Waktu</p>
                            <p>{{ $error->created_at->format('d M Y H:i:s') }}</p>
                            <p class="text-xs text-base-content/50">{{ $error->created_at->diffForHumans() }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-base-content/50 uppercase">Triggered By</p>
                            <p class="font-mono break-all" title="{{ $error->exception_class }}">{{ $triggeredBy }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-base-content/50 uppercase">HTTP Method</p>
                            <p class="font-mono">{{ $error->method ?? '-' }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-xs text-base-content/50 uppercase">File</p>
                            <p class="font-mono text-xs break-all">{{ $error->file ?? '-' }}{{ $error->line ? ':'.$error->line : '' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-base-content/50 uppercase">Route</p>
                            <p class="font-mono text-xs break-all">{{ $error->route_name ?? '-' }}</p>
                        </div>
                    </div>
                    <div class="text-sm">
                        <p class="text-xs text-base-content/50 uppercase mb-1">URL</p>
                        <div class="rounded-lg border border-base-300 bg-base-200 px-3 py-2 font-mono text-xs break-all">
                            @if ($error->url)
                                <a href="{{ $error->url }}" target="_blank" rel="noopener noreferrer" class="link link-primary">{{ $error->url }}</a>
                            @else
                                -
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Info user --}}
            <div class="card bg-base-100 border border-base-300 shadow-sm">
                <div class="card-body p-5 gap-4">
                    <p class="text-xs font-semibold text-base-content/60 uppercase tracking-wider">Info User</p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p class="text-xs text-base-content/50 uppercase">Nama</p>
                            <p class="wrap-break-word">{{ $error->user_name ?? 'Guest' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-base-content/50 uppercase">Email</p>
                            <p class="break-all">{{ $error->user_email ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-base-content/50 uppercase">User ID</p>
                            <p class="font-mono">{{ $error->user_id ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-base-content/50 uppercase">IP Address</p>
                            <p class="font-mono">{{ $ip ?? '-' }}</p>
                        </div>
                    </div>
                    <div class="text-sm">
                        <p class="text-xs text-base-content/50 uppercase mb-1">User Agent</p>
                        <p class="font-mono text-xs text-base-content/70 break-all">{{ $userAgent ?? '-' }}</p>
                    </div>
                </div>
            </div>
        </div>

        @if ($error->stack_trace)
            <div class="card bg-base-100 border border-base-300 shadow-sm">
                <div class="card-body p-5 gap-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-base-content/60 uppercase tracking-wider">Stack Trace</p>
                        <button type="button" class="btn btn-xs btn-ghost" x-on:click="copy('trace', @js($error->stack_trace))"
                            x-text="copied === 'trace' ? 'Tersalin!' : 'Copy'">Copy</button>
                    </div>
                    <pre class="max-h-112 overflow-auto rounded-lg border border-base-300 bg-base-200 p-3 text-xs leading-relaxed">{{ $error->stack_trace }}</pre>
                </div>
            </div>
        @endif

        @if ($context)
            <div class="card bg-base-100 border border-base-300 shadow-sm">
                <div class="card-body p-5 gap-3">
                    <p class="text-xs font-semibold text-base-content/60 uppercase tracking-wider">Context</p>
                    <pre class="max-h-96 overflow-auto rounded-lg border border-base-300 bg-base-200 p-3 text-xs whitespace-pre-wrap">{{ json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        @endif
    </div>
</x-layouts::app.sidebar>
