<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AiData\UserAssertion;
use Illuminate\Console\Command;

/**
 * Prints a short-lived X-AI-User-Assertion for a user's latest AI Center conversation, so the AI service can test
 * /api/ai/* with curl before AI_DATA_MODE is switched to "gateway". It is the same assertion Laravel sends in
 * `user_context`: the API still checks the bearer token, the user's status and their dataset permissions.
 */
class AiDataAssertion extends Command
{
    private const MAX_MINUTES = 60;

    /**
     * @var string
     */
    protected $signature = 'ai-data:assertion
        {user : Email or ID of the user the assertion is for}
        {--minutes=15 : How long the assertion stays valid (max 60)}';

    /**
     * @var string
     */
    protected $description = 'Issue a test X-AI-User-Assertion for the AI data API';

    public function handle(UserAssertion $assertions): int
    {
        if (! $assertions->isConfigured()) {
            $this->error('AI_DATA_ASSERTION_SECRET belum diisi, data API tertutup.');

            return self::FAILURE;
        }

        $user = User::query()
            ->where('email', $this->argument('user'))
            ->when(ctype_digit((string) $this->argument('user')), fn ($query) => $query->orWhere('id', (int) $this->argument('user')))
            ->first();

        if (! $user) {
            $this->error('User tidak ditemukan.');

            return self::FAILURE;
        }

        if (! $user->isActive()) {
            $this->error('User tidak aktif, API akan menolak (403).');

            return self::FAILURE;
        }

        $conversationId = $user->aiConversations()->latest('id')->value('id');

        if ($conversationId === null) {
            $this->error('User belum punya percakapan AI Center. Buka AI Center sekali dengan akun ini, lalu ulangi.');

            return self::FAILURE;
        }

        $minutes = max(1, min(self::MAX_MINUTES, (int) $this->option('minutes')));
        config(['ai_center.data_api.assertion_ttl_seconds' => $minutes * 60]);

        $this->line((string) $assertions->issue($user, $conversationId));
        $this->newLine();
        $this->comment("Berlaku {$minutes} menit untuk {$user->email} (percakapan #{$conversationId}). Kirim di header X-AI-User-Assertion.");

        return self::SUCCESS;
    }
}
