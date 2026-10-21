<?php

namespace App\Services\AiCenter;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to the Nakama "pendampingan" API (`/v1/mapping/*`): a guided chat that walks an employee through mapping
 * their work outputs step by step. The service decides every question; we only relay the user's answers.
 *
 * The service has no per-user access control (the shared API key can read every session), so every session is
 * tagged with the user's pseudonymous `smartid-{id}` and checked against it before it is shown or changed.
 */
class NakamaMentoring
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function units(): array
    {
        return $this->send(fn (PendingRequest $client) => $client->get('/v1/mapping/units'))->json('items', []);
    }

    /**
     * The user's own sessions, most recent first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sessionsFor(User $user, int $limit = 20): array
    {
        $items = $this->send(fn (PendingRequest $client) => $client->get('/v1/mapping/sessions', [
            'user_id' => $this->remoteUserId($user),
            'limit' => $limit,
        ]))->json('items', []);

        $cancelled = $user->aiMentoringCancellations()->pluck('remote_session_id')->all();

        return array_values(array_filter($items, fn (array $session) => $this->isOwnedBy($session, $user)
            && ! in_array($session['id'] ?? null, $cancelled, true)));
    }

    /**
     * Sessions are never anonymous: they carry the logged-in user's name so the answers can be collected per employee.
     *
     * @return array<string, mixed>
     */
    public function start(User $user, string $unit): array
    {
        return $this->send(fn (PendingRequest $client) => $client->post('/v1/mapping/sessions', [
            'unit' => $unit,
            'name' => $user->name,
            'anonymous' => false,
            'user_id' => $this->remoteUserId($user),
        ]))->json();
    }

    /**
     * The session with its chat history. Someone else's session, and one the user cancelled, are reported as not found.
     *
     * @return array<string, mixed>
     */
    public function sessionFor(User $user, string $sessionId): array
    {
        if ($user->aiMentoringCancellations()->where('remote_session_id', $sessionId)->exists()) {
            throw new MentoringException(404, __('Sesi pendampingan ini sudah dibatalkan.'));
        }

        $session = $this->send(fn (PendingRequest $client) => $client->get($this->sessionPath($sessionId)))->json();

        if (! is_array($session) || ! $this->isOwnedBy($session, $user)) {
            throw new MentoringException(404, __('Sesi pendampingan tidak ditemukan.'));
        }

        return $session;
    }

    /**
     * @param  array{text?: ?string, items?: ?array<int, string>, value?: ?string}  $answer
     * @return array<string, mixed>
     */
    public function answer(User $user, string $sessionId, array $answer): array
    {
        $this->ensureNotCompleted($this->sessionFor($user, $sessionId));

        return $this->send(fn (PendingRequest $client) => $client->post($this->sessionPath($sessionId).'/messages', $answer))->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function pause(User $user, string $sessionId): array
    {
        $this->ensureNotCompleted($this->sessionFor($user, $sessionId));

        return $this->send(fn (PendingRequest $client) => $client->post($this->sessionPath($sessionId).'/pause'))->json();
    }

    /**
     * Cancels an unfinished session. A finished one is history and stays. The service has no cancel endpoint, so it
     * is only paused there (best effort) and the cancellation is recorded here; see AiMentoringCancellation.
     */
    public function cancel(User $user, string $sessionId): void
    {
        $session = $this->sessionFor($user, $sessionId);

        if (($session['status'] ?? null) === 'completed') {
            throw new MentoringException(422, __('Sesi yang sudah selesai tidak bisa dibatalkan.'));
        }

        $user->aiMentoringCancellations()->firstOrCreate(
            ['remote_session_id' => $sessionId],
            ['unit_key' => $session['unit']['key'] ?? null],
        );

        if (($session['status'] ?? null) === 'active') {
            try {
                $this->send(fn (PendingRequest $client) => $client->post($this->sessionPath($sessionId).'/pause'));
            } catch (MentoringException) {
                // The cancellation is recorded either way; pausing only tidies up the service's status.
            }
        }
    }

    /**
     * The session summary as a Markdown or JSON file.
     */
    public function export(User $user, string $sessionId, string $format): Response
    {
        $this->sessionFor($user, $sessionId);

        return $this->send(fn (PendingRequest $client) => $client->get($this->sessionPath($sessionId).'/export', ['format' => $format]));
    }

    /**
     * The unit a user maps their work for, taken from their (employee) department. Null lets them pick: Super Admins, and
     * users whose department has no unit (e.g. BoD) or who have no department.
     */
    public function assignedUnitFor(User $user): ?string
    {
        if ($user->hasRole('Super Admin')) {
            return null;
        }

        // The employee record is the source of the department; users.department_id is only a synced copy and is
        // often still empty, so it is the fallback.
        $code = ($user->employee?->department ?? $user->department)?->code;

        return $code ? (config('ai_center.mentoring.units_by_department_code')[$code] ?? null) : null;
    }

    public function remoteUserId(User $user): string
    {
        return 'smartid-'.$user->id;
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function isOwnedBy(array $session, User $user): bool
    {
        return ($session['user_id'] ?? null) === $this->remoteUserId($user);
    }

    /**
     * A finished session is history: it can be read and exported, never answered again.
     *
     * @param  array<string, mixed>  $session
     */
    private function ensureNotCompleted(array $session): void
    {
        if (($session['status'] ?? null) === 'completed') {
            throw new MentoringException(422, __('Sesi ini sudah selesai dan hanya bisa dibaca.'));
        }
    }

    private function sessionPath(string $sessionId): string
    {
        return '/v1/mapping/sessions/'.rawurlencode($sessionId);
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     */
    private function send(callable $request): Response
    {
        try {
            $response = $request($this->client());
        } catch (ConnectionException $e) {
            Log::warning('Nakama mentoring connection failed', ['error' => $e->getMessage()]);

            throw new MentoringException(504, __('Layanan pendampingan sedang tidak dapat dihubungi. Coba lagi sebentar lagi.'));
        }

        if ($response->failed()) {
            throw match (true) {
                $response->status() === 404 => new MentoringException(404, __('Sesi pendampingan tidak ditemukan.')),
                in_array($response->status(), [400, 409, 422], true) => new MentoringException(422, (string) ($response->json('error.message') ?? __('Jawaban belum bisa diproses. Periksa lagi isiannya ya.'))),
                default => new MentoringException(502, __('Terjadi kesalahan pada layanan pendampingan, coba lagi ya.')),
            };
        }

        return $response;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('ai_center.nakama.url'), '/'))
            ->withHeaders(['X-API-Key' => (string) config('ai_center.nakama.api_key')])
            ->acceptJson()
            ->connectTimeout(config('ai_center.timeouts.connect'))
            ->timeout(config('ai_center.timeouts.total'));
    }
}
