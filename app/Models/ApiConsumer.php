<?php

namespace App\Models;

use Database\Factories\ApiConsumerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A website/app allowed to call the public lookup API (/api/opds, /api/regencies, ...) with its own token
 * (header X-API-Key). Only the SHA-256 of the token is stored.
 *
 * @property list<string> $allowed_origins hosts such as "app.example.com", "*.example.com" or "localhost:3000"
 */
class ApiConsumer extends Model
{
    /** @use HasFactory<ApiConsumerFactory> */
    use HasFactory;

    public const TOKEN_PREFIX = 'smid_';

    public const HEADER = 'X-API-Key';

    /**
     * How long a token lookup is cached. Saving or deleting a consumer clears its entry right away.
     */
    public const CACHE_SECONDS = 600;

    /**
     * last_used_at is written at most once per this many seconds per consumer.
     */
    public const USAGE_WRITE_INTERVAL_SECONDS = 300;

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_origins' => 'array',
            'allow_server_requests' => 'boolean',
            'rate_limit_per_minute' => 'integer',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (ApiConsumer $consumer) {
            Cache::forget(self::cacheKey($consumer->token_hash));

            if ($consumer->wasChanged('token_hash') && $consumer->getOriginal('token_hash')) {
                Cache::forget(self::cacheKey($consumer->getOriginal('token_hash')));
            }
        });

        static::deleted(fn (ApiConsumer $consumer) => Cache::forget(self::cacheKey($consumer->token_hash)));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Replaces the token and returns the new plain token; it cannot be read back later.
     */
    public function issueToken(): string
    {
        $token = self::TOKEN_PREFIX.Str::random(40);

        $this->forceFill([
            'token_prefix' => substr($token, 0, 12),
            'token_hash' => self::hashToken($token),
        ]);

        return $token;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * The settings the API middleware needs for a token, cached so a request costs no query on a cache hit.
     *
     * @return array{id: int, name: string, revoked: bool, origins: list<string>, allow_server: bool, limit: int}|null
     */
    public static function lookup(string $token): ?array
    {
        if (! str_starts_with($token, self::TOKEN_PREFIX)) {
            return null;
        }

        $hash = self::hashToken($token);
        $cached = Cache::get(self::cacheKey($hash));

        if (is_array($cached)) {
            return $cached;
        }

        $consumer = self::query()->where('token_hash', $hash)->first();

        if (! $consumer) {
            return null;
        }

        $settings = [
            'id' => $consumer->id,
            'name' => $consumer->name,
            'revoked' => $consumer->isRevoked(),
            'origins' => array_values($consumer->allowed_origins ?? []),
            'allow_server' => $consumer->allow_server_requests,
            'limit' => max(1, $consumer->rate_limit_per_minute),
        ];

        Cache::put(self::cacheKey($hash), $settings, self::CACHE_SECONDS);

        return $settings;
    }

    /**
     * "https://App.Example.com:8080/path" → "app.example.com:8080"; "*.example.com" stays a wildcard. Null when the
     * value holds no host.
     */
    public static function normalizeOrigin(?string $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));

        if ($value === '' || $value === 'null') {
            return null;
        }

        $wildcard = str_starts_with($value, '*.');
        $parts = parse_url(str_contains($value, '://') ? $value : 'http://'.($wildcard ? substr($value, 2) : $value));
        $host = $parts['host'] ?? null;

        if (! $host) {
            return null;
        }

        return ($wildcard ? '*.' : '').$host.(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /**
     * @param  list<string>  $allowedOrigins
     */
    public static function originIsAllowed(string $origin, array $allowedOrigins): bool
    {
        foreach ($allowedOrigins as $allowed) {
            if ($allowed === $origin) {
                return true;
            }

            if (str_starts_with($allowed, '*.') && str_ends_with($origin, substr($allowed, 1))) {
                return true;
            }
        }

        return false;
    }

    private static function cacheKey(string $hash): string
    {
        return "api-consumer:{$hash}";
    }
}
