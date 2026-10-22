<?php

namespace App\Http\Middleware;

use App\Models\ApiConsumer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function Illuminate\Support\defer;

/**
 * Public lookup API (/api/opds, /api/regencies, ...): the caller sends its token in X-API-Key, and a browser call
 * must come from one of the consumer's registered websites (Origin, else Referer). Calls without either header
 * (a backend) are only accepted when the consumer allows server requests. This site's own pages (the documentation
 * playground) are always accepted.
 *
 * The token lookup is cached (ApiConsumer::lookup) and the "last used" write runs after the response is sent, so
 * a valid request adds no database write and, on a cache hit, no query.
 */
class AuthenticateApiConsumer
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = trim((string) $request->header(ApiConsumer::HEADER));

        if ($token === '') {
            return $this->reject(401, 'API key wajib dikirim di header '.ApiConsumer::HEADER.'.');
        }

        $consumer = ApiConsumer::lookup($token);

        if (! $consumer || $consumer['revoked']) {
            return $this->reject(401, 'API key tidak valid atau sudah dicabut.');
        }

        $origin = $this->origin($request);

        if ($origin === null && ! $consumer['allow_server']) {
            return $this->reject(403, 'API key ini hanya boleh dipakai dari website yang terdaftar.');
        }

        if ($origin !== null && ! $this->isOwnOrigin($request, $origin) && ! ApiConsumer::originIsAllowed($origin, $consumer['origins'])) {
            return $this->reject(403, "Website {$origin} tidak terdaftar untuk API key ini.");
        }

        $request->attributes->set('api_consumer', $consumer);

        defer(fn () => $this->recordUsage($consumer['id'], $origin));

        return $next($request);
    }

    private function origin(Request $request): ?string
    {
        return ApiConsumer::normalizeOrigin($request->header('Origin'))
            ?? ApiConsumer::normalizeOrigin($request->header('Referer'));
    }

    /**
     * A call from this site's own pages: same host as APP_URL, or as the request itself (the site is often opened
     * under another host than APP_URL, e.g. smartdashboard.test vs localhost:8000).
     */
    private function isOwnOrigin(Request $request, string $origin): bool
    {
        return in_array($origin, [
            ApiConsumer::normalizeOrigin(config('app.url')),
            ApiConsumer::normalizeOrigin($request->getSchemeAndHttpHost()),
        ], true);
    }

    private function recordUsage(int $consumerId, ?string $origin): void
    {
        if (! cache()->add("api-consumer-used:{$consumerId}", true, ApiConsumer::USAGE_WRITE_INTERVAL_SECONDS)) {
            return;
        }

        ApiConsumer::query()->whereKey($consumerId)->update([
            'last_used_at' => now(),
            'last_used_origin' => $origin ?? 'server',
        ]);
    }

    private function reject(int $status, string $message): Response
    {
        return response()->json(
            ['message' => $message, 'documentation' => route('tech.documentation').'#api-key'],
            $status,
            $status === 401 ? ['WWW-Authenticate' => 'ApiKey header="'.ApiConsumer::HEADER.'"'] : [],
        );
    }
}
