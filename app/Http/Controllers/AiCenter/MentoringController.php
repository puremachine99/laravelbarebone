<?php

namespace App\Http\Controllers\AiCenter;

use App\Http\Controllers\Controller;
use App\Services\AiCenter\MentoringException;
use App\Services\AiCenter\NakamaMentoring;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * JSON relay between the "Pendampingan" tab of the AI Center and the Nakama mapping API, so the API key never
 * reaches the browser and a user only ever sees their own sessions.
 */
class MentoringController extends Controller
{
    public function __construct(private NakamaMentoring $mentoring) {}

    public function units(): JsonResponse
    {
        return $this->relay(fn () => ['items' => $this->mentoring->units()]);
    }

    public function index(Request $request): JsonResponse
    {
        return $this->relay(fn () => ['items' => $this->mentoring->sessionsFor($request->user())]);
    }

    public function store(Request $request): JsonResponse
    {
        $assignedUnit = $this->mentoring->assignedUnitFor($request->user());

        $unit = $assignedUnit ?? $request->validate([
            'unit' => ['required', 'string', 'max:64'],
        ])['unit'];

        return $this->relay(fn () => $this->mentoring->start($request->user(), $unit), 201);
    }

    public function show(Request $request, string $sessionId): JsonResponse
    {
        return $this->relay(fn () => $this->mentoring->sessionFor($request->user(), $sessionId));
    }

    public function answer(Request $request, string $sessionId): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['nullable', 'string', 'max:4000'],
            'items' => ['nullable', 'array', 'max:30'],
            'items.*' => ['string', 'max:300'],
            'value' => ['nullable', 'string', 'max:32'],
        ]);

        if (blank($validated['text'] ?? null) && empty($validated['items']) && blank($validated['value'] ?? null)) {
            return response()->json(['message' => __('Isi jawabanmu dulu ya.')], 422);
        }

        return $this->relay(fn () => $this->mentoring->answer($request->user(), $sessionId, $validated));
    }

    public function pause(Request $request, string $sessionId): JsonResponse
    {
        return $this->relay(fn () => $this->mentoring->pause($request->user(), $sessionId));
    }

    /**
     * Only an unfinished session can be cancelled; it then disappears from the history.
     */
    public function cancel(Request $request, string $sessionId): JsonResponse
    {
        return $this->relay(function () use ($request, $sessionId) {
            $this->mentoring->cancel($request->user(), $sessionId);

            return ['cancelled' => true];
        });
    }

    public function export(Request $request, string $sessionId): Response|JsonResponse
    {
        $format = $request->validate(['format' => ['required', Rule::in(['markdown', 'json'])]])['format'];

        try {
            $file = $this->mentoring->export($request->user(), $sessionId, $format);
        } catch (MentoringException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        $extension = $format === 'json' ? 'json' : 'md';

        return response($file->body(), 200, [
            'Content-Type' => $format === 'json' ? 'application/json' : 'text/markdown; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="ringkasan-pendampingan-'.substr(preg_replace('/[^a-z0-9]/i', '', $sessionId), -8).'.'.$extension.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param  callable(): array<string, mixed>  $call
     */
    private function relay(callable $call, int $status = 200): JsonResponse
    {
        try {
            return response()->json($call(), $status);
        } catch (MentoringException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }
    }
}
