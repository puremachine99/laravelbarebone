<?php

namespace App\Console\Commands;

use App\Services\AiCenter\AiChatRequest;
use App\Services\AiCenter\AiGateway;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Checks from the server itself whether the AI service is reachable and answers, using the same config and gateway
 * the AI Center uses. For diagnosing "Layanan AI sedang lambat" in production.
 */
class AiPing extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ai:ping
        {message=halo : Message to send to the AI}
        {--skip-chat : Only check that the host is reachable, without sending a message}';

    /**
     * @var string
     */
    protected $description = 'Test connectivity and response time of the configured AI Center service';

    public function handle(AiGateway $gateway): int
    {
        $driver = (string) config('ai_center.driver');
        $url = (string) config('ai_center.nakama.url');

        $this->line("Driver : {$driver}");
        $this->line("URL    : {$url}");
        $this->line('Model  : '.(config('ai_center.nakama.model') ?: '-'));
        $this->line('Timeout: connect '.config('ai_center.timeouts.connect').'s, total '.config('ai_center.timeouts.total').'s');
        $this->newLine();

        if ($driver === 'nakama' && ! $this->checkHost($url)) {
            return self::FAILURE;
        }

        if ($this->option('skip-chat')) {
            return self::SUCCESS;
        }

        return $this->checkChat($gateway);
    }

    private function checkHost(string $url): bool
    {
        $start = microtime(true);

        try {
            $response = Http::connectTimeout((int) config('ai_center.timeouts.connect'))->timeout(20)->get($url);
        } catch (ConnectionException $e) {
            $this->error('1. Host tidak terjangkau: '.$e->getMessage());

            return false;
        }

        $this->info(sprintf('1. Host terjangkau (HTTP %d, %d ms)', $response->status(), (microtime(true) - $start) * 1000));

        return true;
    }

    private function checkChat(AiGateway $gateway): int
    {
        $request = new AiChatRequest(
            requestId: (string) Str::uuid(),
            userId: 0,
            userName: 'ai-ping',
            conversationId: 0,
            message: (string) $this->argument('message'),
        );

        $start = microtime(true);
        $firstTokenMs = null;
        $answer = '';

        foreach ($gateway->stream($request) as $event) {
            if ($event->type === 'delta') {
                $firstTokenMs ??= (int) ((microtime(true) - $start) * 1000);
                $answer .= $event->text;
            }

            if ($event->type === 'error') {
                $this->error('2. Gagal: '.$event->errorCode?->value.' — '.$event->errorCode?->message());
                $this->line('   Detail error koneksi ada di storage/logs/laravel.log ("Nakama AI connection failed").');

                return self::FAILURE;
            }
        }

        $this->info(sprintf('2. Chat berhasil (token pertama %s ms, total %d ms)', $firstTokenMs ?? '-', (microtime(true) - $start) * 1000));
        $this->line('   Jawaban: '.Str::limit(trim($answer), 120));

        return self::SUCCESS;
    }
}
