<?php

namespace App\Services\AiCenter;

use App\Enums\AiErrorCode;
use Illuminate\Http\StreamedEvent;

/**
 * One event coming out of AiGateway::stream(): a piece of the answer, the end of it, or an error.
 */
final readonly class AiStreamEvent
{
    /**
     * @param  array{prompt_tokens?: int, output_tokens?: int, total_tokens?: int}  $usage
     */
    private function __construct(
        public string $type,
        public string $text = '',
        public ?AiErrorCode $errorCode = null,
        public array $usage = [],
        public ?string $model = null,
        public ?string $finishReason = null,
        public ?string $remoteConversationId = null,
        public ?string $remoteFileId = null,
        public ?string $fileName = null,
        public ?string $fileMimeType = null,
    ) {}

    /**
     * Internal only (never sent to the browser): the AI service's id for this thread.
     */
    public static function started(string $remoteConversationId): self
    {
        return new self('started', remoteConversationId: $remoteConversationId);
    }

    public function isStarted(): bool
    {
        return $this->type === 'started';
    }

    /**
     * A file the AI generated (document or image) and stored on the AI service.
     */
    public static function file(string $remoteFileId, string $name, string $mimeType): self
    {
        return new self('file', remoteFileId: $remoteFileId, fileName: $name, fileMimeType: $mimeType);
    }

    public function isFile(): bool
    {
        return $this->type === 'file';
    }

    public static function delta(string $text): self
    {
        return new self('delta', text: $text);
    }

    /**
     * @param  array{prompt_tokens?: int, output_tokens?: int, total_tokens?: int}  $usage
     */
    public static function done(array $usage = [], ?string $model = null, ?string $finishReason = null): self
    {
        return new self('done', usage: $usage, model: $model, finishReason: $finishReason);
    }

    public static function error(AiErrorCode $code): self
    {
        return new self('error', errorCode: $code);
    }

    public function isDelta(): bool
    {
        return $this->type === 'delta';
    }

    public function isDone(): bool
    {
        return $this->type === 'done';
    }

    public function isError(): bool
    {
        return $this->type === 'error';
    }

    /**
     * The SSE event sent to the browser. Usage numbers stay server-side.
     */
    public function toStreamedEvent(): StreamedEvent
    {
        return new StreamedEvent($this->type, match ($this->type) {
            'delta' => ['text' => $this->text],
            'error' => ['code' => $this->errorCode->value, 'message' => $this->errorCode->message()],
            default => ['finish_reason' => $this->finishReason],
        });
    }
}
