<?php

namespace App\Services\AiCenter;

use App\Enums\AiErrorCode;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Talks to the Nakama LLM API (`POST /v1/chat`, Server-Sent Events). The AI service keeps the thread history
 * itself, so only the new message and the service's thread id are sent. All of the service's tools (web search,
 * file/image/document generation, memory) are switched off unless enabled in config/ai_center.php.
 *
 * Only a pseudonymous `smartid-{id}` user id is sent — never the user's name or email.
 */
class NakamaAiGateway implements AiGateway
{
    public function stream(AiChatRequest $request): iterable
    {
        $finished = false;
        $remoteId = $request->remoteConversationId;

        try {
            $response = $this->client()
                ->withOptions(['stream' => true])
                ->post('/v1/chat', $this->payload($request));

            if ($response->failed()) {
                yield AiStreamEvent::error($this->errorCodeFor($response->status(), $this->errorCodeFromBody($response)));
                $finished = true;

                return;
            }

            foreach ($this->events($response->toPsrResponse()->getBody()) as [$name, $data]) {
                switch ($name) {
                    case 'start':
                        $remoteId = $data['conversation_id'] ?? $remoteId;

                        if ($remoteId && $remoteId !== $request->remoteConversationId) {
                            yield AiStreamEvent::started($remoteId);
                        }
                        break;

                    case 'text_delta':
                        if (($data['text'] ?? '') !== '') {
                            yield AiStreamEvent::delta($data['text']);
                        }
                        break;

                    case 'error':
                        $finished = true;

                        yield AiStreamEvent::error($this->errorCodeFor((int) ($data['status'] ?? 502), $data['code'] ?? null));

                        return;

                    case 'done':
                        $finished = true;
                        $tokens = $data['message']['tokens'] ?? $data['usage'] ?? [];
                        $input = (int) ($tokens['input_tokens'] ?? 0);
                        $output = (int) ($tokens['output_tokens'] ?? 0);

                        yield AiStreamEvent::done(
                            usage: [
                                'prompt_tokens' => $input,
                                'output_tokens' => $output,
                                'total_tokens' => (int) ($tokens['total_tokens'] ?? $input + $output),
                            ],
                            model: $data['message']['model'] ?? null,
                            finishReason: $data['stop_reason'] ?? null,
                        );

                        return;
                }
            }

            // The stream ended without a `done` event: the connection was cut mid-answer.
            $finished = true;

            yield AiStreamEvent::error(AiErrorCode::UpstreamError);
        } catch (ConnectionException $e) {
            $finished = true;

            Log::warning('Nakama AI connection failed', [
                'url' => config('ai_center.nakama.url'),
                'conversation_id' => $request->conversationId,
                'error' => $e->getMessage(),
            ]);

            yield AiStreamEvent::error(AiErrorCode::UpstreamTimeout);
        } finally {
            // Browser hung up (Stop button) before the answer finished: tell the service to stop generating.
            if (! $finished && $remoteId) {
                $this->cancel($remoteId);
            }
        }
    }

    public function forget(string $remoteConversationId): void
    {
        try {
            $this->client()->delete('/v1/conversations/'.rawurlencode($remoteConversationId));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function cancel(string $remoteConversationId): void
    {
        try {
            $this->client()->timeout(5)->post('/v1/chat/'.rawurlencode($remoteConversationId).'/cancel');
        } catch (Throwable) {
            // Best effort: the service finishes and saves the answer on its own anyway.
        }
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('ai_center.nakama.url'), '/'))
            ->withHeaders(['X-API-Key' => (string) config('ai_center.nakama.api_key')])
            ->acceptJson()
            ->connectTimeout(config('ai_center.timeouts.connect'))
            ->timeout(config('ai_center.timeouts.total'));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AiChatRequest $request): array
    {
        return array_filter([
            'conversation_id' => $request->remoteConversationId,
            'message' => $request->message,
            'user_id' => 'smartid-'.$request->userId,
            'session_id' => 'ai-center-'.$request->conversationId,
            'provider' => config('ai_center.nakama.provider'),
            'model' => config('ai_center.nakama.model'),
            'system_prompt' => $request->systemPrompt ?? config('ai_center.nakama.system_prompt'),
            'effort' => config('ai_center.nakama.effort'),
            // Gateway data mode only: department/roles for routing, the tools this user may call, and the signed
            // assertion the service must forward in the X-AI-User-Assertion header (never to the model).
            'user_context' => $request->userContext,
        ], fn ($value) => $value !== null && $value !== '') + [
            'stream' => true,
            'include_thinking' => false,
            'tools' => array_map(boolval(...), config('ai_center.nakama.tools')),
        ];
    }

    /**
     * Parses an SSE body into [event name, decoded JSON data] pairs. Comment lines (`: keep-alive`) are skipped.
     *
     * @return Generator<int, array{0: string, 1: array<string, mixed>}>
     */
    private function events(StreamInterface $body): Generator
    {
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= str_replace("\r\n", "\n", $body->read(1024));

            while (($end = strpos($buffer, "\n\n")) !== false) {
                $block = substr($buffer, 0, $end);
                $buffer = substr($buffer, $end + 2);

                $name = 'message';
                $data = '';

                foreach (explode("\n", $block) as $line) {
                    if (str_starts_with($line, 'event:')) {
                        $name = trim(substr($line, 6));
                    } elseif (str_starts_with($line, 'data:')) {
                        $data .= ltrim(substr($line, 5));
                    }
                }

                $decoded = json_decode($data, true);

                if (is_array($decoded)) {
                    yield [$name, $decoded];
                }
            }
        }
    }

    private function errorCodeFromBody(Response $response): ?string
    {
        return json_decode((string) $response->toPsrResponse()->getBody(), true)['error']['code'] ?? null;
    }

    /**
     * 401/404/409/5xx are our own configuration or availability problems, not something the user can act on.
     */
    private function errorCodeFor(int $status, ?string $serviceCode): AiErrorCode
    {
        return match (true) {
            $status === 429 => AiErrorCode::UpstreamQuota,
            $status === 408, $status === 504 => AiErrorCode::UpstreamTimeout,
            $status === 400, $serviceCode === 'blocked' => AiErrorCode::UpstreamBlocked,
            default => AiErrorCode::UpstreamError,
        };
    }
}
