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
        $lastStatus = null;

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

                    case 'thinking_delta':
                    case 'tool_call':
                        $status = $this->statusFor($name, $data);

                        if ($status->text !== $lastStatus) {
                            $lastStatus = $status->text;

                            yield $status;
                        }
                        break;

                    case 'text_delta':
                        if (($data['text'] ?? '') !== '') {
                            yield AiStreamEvent::delta($data['text']);
                        }
                        break;

                    case 'document':
                    case 'image':
                        if (($data['file_id'] ?? '') !== '') {
                            $mime = $data['mime_type'] ?? 'application/octet-stream';

                            yield AiStreamEvent::file(
                                $data['file_id'],
                                $data['filename'] ?? 'gambar.'.(explode('/', $mime)[1] ?? 'png'),
                                $mime,
                            );
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

    public function uploadFiles(array $files): array
    {
        $upload = $this->client()->timeout(config('ai_center.timeouts.total'))->asMultipart();

        foreach ($files as $file) {
            $upload = $upload->attach('files', (string) file_get_contents($file->getRealPath()), $file->getClientOriginalName());
        }

        try {
            $response = $upload->post('/v1/files');
        } catch (ConnectionException $e) {
            Log::warning('Nakama AI file upload failed to connect', ['error' => $e->getMessage()]);

            throw new AiFileUploadException(AiErrorCode::UpstreamTimeout);
        }

        if ($response->failed()) {
            throw new AiFileUploadException($this->uploadErrorCodeFor($response->status(), $this->errorCodeFromBody($response)));
        }

        return array_map(fn (array $file) => [
            'id' => (string) $file['id'],
            'name' => (string) ($file['filename'] ?? 'file'),
            'mime_type' => (string) ($file['mime_type'] ?? 'application/octet-stream'),
            'size_bytes' => isset($file['size_bytes']) ? (int) $file['size_bytes'] : null,
        ], $response->json('files', []));
    }

    public function generateDocument(array $document): array
    {
        try {
            $response = $this->client()->post('/v1/files/generate', $document);
        } catch (ConnectionException $e) {
            Log::warning('Nakama AI document generation failed to connect', ['error' => $e->getMessage()]);

            throw new AiFileUploadException(AiErrorCode::UpstreamTimeout);
        }

        if ($response->failed()) {
            throw new AiFileUploadException($this->uploadErrorCodeFor($response->status(), $this->errorCodeFromBody($response)));
        }

        return [
            'id' => (string) $response->json('id'),
            'name' => (string) $response->json('filename', $document['filename']),
            'mime_type' => (string) $response->json('mime_type', 'application/octet-stream'),
            'size_bytes' => $response->json('size_bytes') !== null ? (int) $response->json('size_bytes') : null,
        ];
    }

    /**
     * The service answers with a 307 to a presigned storage URL; that second hop must not carry our API key.
     */
    public function downloadFile(string $remoteFileId): ?StreamInterface
    {
        try {
            $response = $this->client()
                ->withoutRedirecting()
                ->withOptions(['stream' => true])
                ->get('/v1/files/'.rawurlencode($remoteFileId).'/content');

            if ($response->redirect() && $response->header('Location') !== '') {
                $response = Http::withOptions(['stream' => true])
                    ->timeout(config('ai_center.timeouts.total'))
                    ->get($response->header('Location'));
            }

            return $response->successful() ? $response->toPsrResponse()->getBody() : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function deleteFile(string $remoteFileId): void
    {
        try {
            $this->client()->delete('/v1/files/'.rawurlencode($remoteFileId));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The progress line for an upstream `thinking_delta` or `tool_call` event.
     *
     * @param  array<string, mixed>  $data
     */
    private function statusFor(string $event, array $data): AiStreamEvent
    {
        if ($event === 'thinking_delta') {
            return AiStreamEvent::status('thinking', __('Sedang berpikir…'));
        }

        $tool = (string) ($data['name'] ?? '');

        return match (true) {
            $tool === 'nakama_data' => AiStreamEvent::status('data', __('Mengambil data dari sistem…')),
            $tool === 'create_document' => AiStreamEvent::status('document', match ($data['input']['format'] ?? null) {
                'docx' => __('Menyusun dokumen Word…'),
                'pdf' => __('Menyusun dokumen PDF…'),
                'pptx' => __('Menyusun presentasi…'),
                default => isset($data['input']['sheets']) ? __('Menyusun file Excel…') : __('Menyusun dokumen…'),
            }),
            str_contains($tool, 'image') => AiStreamEvent::status('image', __('Membuat gambar…')),
            str_contains($tool, 'search'), str_contains($tool, 'fetch') => AiStreamEvent::status('web', __('Mencari informasi di web…')),
            default => AiStreamEvent::status('tool', __('Memproses permintaan…')),
        };
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
            'file_ids' => $request->fileIds ?: null,
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

    private function uploadErrorCodeFor(int $status, ?string $serviceCode): AiErrorCode
    {
        return match (true) {
            $status === 413 => AiErrorCode::FileTooLarge,
            $status === 415 => AiErrorCode::FileUnsupported,
            $status === 422 && in_array($serviceCode, ['sensitive_data_detected', 'forbidden_file', 'face_detected'], true) => AiErrorCode::FileSensitive,
            $status === 422 => AiErrorCode::FileInvalid,
            $status === 429 => AiErrorCode::UpstreamQuota,
            default => AiErrorCode::UpstreamError,
        };
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
