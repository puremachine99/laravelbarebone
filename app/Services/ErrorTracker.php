<?php

namespace App\Services;

use App\Models\ErrorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ErrorTracker
{
    /**
     * Sensitive keys that must never be persisted as-is.
     *
     * @var array<int, string>
     */
    protected const REDACTED_KEYS = ['password', 'password_confirmation', 'token', 'secret', 'api_key'];

    /**
     * Capture an error into the error_logs table. Never throws — a failure here
     * must not take down the request/page that triggered it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function capture(array $attributes): ?ErrorLog
    {
        try {
            $user = auth()->user();

            // Who/where the error came from, kept in context so no extra columns are needed.
            $attributes['context'] = array_merge([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ], $attributes['context'] ?? []);

            return ErrorLog::create(array_merge([
                'error_code' => Str::upper(Str::random(8)),
                'type' => 'exception',
                'severity' => 'error',
                'resolved' => false,
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'user_name' => $user?->name,
            ], self::redact($attributes)));
        } catch (Throwable $e) {
            Log::warning('ErrorTracker failed to capture error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Capture a backend HTTP/exception error thrown during a request.
     */
    public static function captureException(Throwable $e, Request $request): ?ErrorLog
    {
        $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

        return self::capture([
            'type' => 'http',
            'status' => $status,
            'severity' => $status >= 500 ? 'critical' : 'warning',
            'message' => $e->getMessage() ?: class_basename($e),
            'exception_class' => get_class($e),
            'stack_trace' => $e->getTraceAsString(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'url' => $request->fullUrl(),
            'route_name' => $request->route()?->getName(),
            'method' => $request->method(),
            'context' => [
                'input' => $request->all(),
            ],
        ]);
    }

    /**
     * Capture a JavaScript error reported from the browser.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function captureFromBrowser(array $payload, Request $request): ?ErrorLog
    {
        return self::capture([
            'type' => 'javascript',
            'severity' => 'error',
            'message' => $payload['message'] ?? 'Unknown JavaScript error',
            'stack_trace' => $payload['stack'] ?? null,
            'file' => $payload['file'] ?? null,
            'line' => isset($payload['line']) ? (int) $payload['line'] : null,
            'column' => isset($payload['column']) ? (int) $payload['column'] : null,
            'url' => $payload['url'] ?? $request->headers->get('referer'),
            'method' => $request->method(),
            'context' => [
                'payload' => $payload,
            ],
        ]);
    }

    /**
     * Recursively redact sensitive keys from the given attributes' nested arrays.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected static function redact(array $attributes): array
    {
        if (isset($attributes['context']) && is_array($attributes['context'])) {
            $attributes['context'] = self::redactRecursive($attributes['context']);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function redactRecursive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::redactRecursive($value);

                continue;
            }

            if (is_string($key) && in_array(strtolower($key), self::REDACTED_KEYS, true)) {
                $data[$key] = '[redacted]';
            }
        }

        return $data;
    }
}
