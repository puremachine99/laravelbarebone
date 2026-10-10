<?php

namespace App\Http\Controllers\Debug;

use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use App\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ErrorLogController extends Controller
{
    /**
     * Sentry page: the error log list, or (?tab=force-login) the force login tool.
     */
    public function index(Request $request): View
    {
        if ($request->query('tab') === 'force-login') {
            return view('debug.errors.index', ['tab' => 'force-login']);
        }

        $this->purgeOldErrorsWeekly();

        $errors = ErrorLog::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->integer('status')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->string('severity')))
            ->when($request->filled('resolved'), fn ($q) => $q->where('resolved', $request->string('resolved') === '1'))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%'.$request->string('search').'%';
                $q->where(function ($sub) use ($search) {
                    $sub->where('error_code', 'like', $search)
                        ->orWhere('message', 'like', $search)
                        ->orWhere('user_email', 'like', $search)
                        ->orWhere('user_name', 'like', $search)
                        ->orWhere('route_name', 'like', $search);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(PerPage::resolve($request->query('per_page')))
            ->withQueryString();

        $stats = [
            'total' => ErrorLog::count(),
            'today' => ErrorLog::whereDate('created_at', today())->count(),
            'unresolved' => ErrorLog::unresolved()->count(),
            'critical_unresolved' => ErrorLog::unresolved()->critical()->count(),
        ];

        return view('debug.errors.index', [
            'tab' => 'errors',
            'errors' => $errors,
            'stats' => $stats,
            'filters' => $request->only(['type', 'status', 'severity', 'resolved', 'search']),
        ]);
    }

    public function show(string $code): View
    {
        $error = ErrorLog::where('error_code', $code)->firstOrFail();

        return view('debug.errors.show', ['error' => $error]);
    }

    public function resolve(int $id): JsonResponse
    {
        $error = ErrorLog::findOrFail($id);
        $error->update([
            'resolved' => ! $error->resolved,
            'resolved_at' => ! $error->resolved ? now() : null,
        ]);

        return response()->json(['resolved' => $error->resolved]);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        ErrorLog::findOrFail($id)->delete();

        // Deleting from the detail page can't go "back" to a page that no longer exists.
        if ($request->boolean('to_index')) {
            return redirect()->route('dev.errors.index')->with('status', 'Log error berhasil dihapus.');
        }

        return back()->with('status', 'Log error berhasil dihapus.');
    }

    public function purge(): RedirectResponse
    {
        ErrorLog::query()->delete();

        return back()->with('status', 'Semua log error berhasil dihapus.');
    }

    /**
     * Piggyback weekly housekeeping on a page view instead of a scheduler:
     * the first request each week purges logs older than 1 week, guarded by
     * a cache lock so concurrent requests only run it once.
     */
    protected function purgeOldErrorsWeekly(): void
    {
        $lock = Cache::lock('error_logs_weekly_purge', 60);

        if (! $lock->get()) {
            return;
        }

        try {
            if (Cache::add('error_logs_weekly_purge_marker', true, now()->addWeek())) {
                ErrorLog::where('created_at', '<', now()->subWeek())->delete();
            }
        } finally {
            $lock->release();
        }
    }
}
