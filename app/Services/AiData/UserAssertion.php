<?php

namespace App\Services\AiData;

use App\Models\User;

/**
 * Short-lived proof of "this data request is for user X in AI Center conversation Y", in the form
 * `<base64url payload>.<base64url HMAC-SHA256 signature>`. The payload ({"uid", "cid", "exp"}) is readable but cannot
 * be changed without the secret, which only Laravel has; so whoever holds the API bearer token still cannot act as
 * another user. The AI service only forwards it in the X-AI-User-Assertion header and never shows it to the model.
 */
class UserAssertion
{
    public function isConfigured(): bool
    {
        return $this->secret() !== '';
    }

    /**
     * null when no secret is configured (the data API is then closed).
     */
    public function issue(User $user, int $conversationId): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $payload = $this->encode(json_encode([
            'uid' => $user->id,
            'cid' => $conversationId,
            'exp' => now()->addSeconds(config('ai_center.data_api.assertion_ttl_seconds'))->timestamp,
        ]));

        return $payload.'.'.$this->sign($payload);
    }

    /**
     * The user and conversation ids, or null when the assertion is malformed, tampered with, signed with another
     * secret, or expired.
     *
     * @return array{user_id: int, conversation_id: int}|null
     */
    public function verify(string $assertion): ?array
    {
        $parts = explode('.', $assertion);

        if (! $this->isConfigured() || count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;

        if (! hash_equals($this->sign($payload), $signature)) {
            return null;
        }

        $claims = json_decode($this->decode($payload), true);

        if (! is_int($claims['uid'] ?? null) || ! is_int($claims['cid'] ?? null) || ! is_int($claims['exp'] ?? null)) {
            return null;
        }

        if ($claims['exp'] < now()->timestamp) {
            return null;
        }

        return ['user_id' => $claims['uid'], 'conversation_id' => $claims['cid']];
    }

    private function sign(string $payload): string
    {
        return $this->encode(hash_hmac('sha256', $payload, $this->secret(), true));
    }

    private function secret(): string
    {
        return (string) config('ai_center.data_api.assertion_secret');
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
