<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    @php($title = 'Debug Panel Login')
    @include('partials.head')
</head>

<body class="min-h-screen bg-neutral-950 text-neutral-100 antialiased flex items-center justify-center p-6">
    <div class="w-full max-w-sm space-y-6">
        <div class="text-center space-y-1">
            <h1 class="text-xl font-bold">Error Tracker</h1>
            <p class="text-sm text-neutral-400">Internal dev-only panel. Not part of the main application auth.</p>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-red-900 bg-red-950/50 px-4 py-3 text-sm text-red-300">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('dev.login.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-xs font-medium text-neutral-400">Password</label>
                <input type="password" name="password" autofocus required
                    class="w-full rounded-lg border border-neutral-700 bg-neutral-900 px-3 py-2 text-sm text-neutral-100 focus:border-indigo-500 focus:outline-none" />
            </div>
            <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                Masuk
            </button>
        </form>
    </div>
</body>

</html>
