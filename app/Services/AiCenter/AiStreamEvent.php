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
        public ?string $phase = null,
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

    /**
     * What the AI is busy with before (or between) pieces of the answer: thinking, fetching data, building a file…
     * The browser shows `$label` in place of a generic "typing" indicator. `$phase` lets the UI pick an icon.
     */
    public static function status(string $phase, string $label): self
    {
        return new self('status', text: $label, phase: $phase);
    }

    public function isStatus(): bool
    {
        return $this->type === 'status';
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

    /**
     * @param  ?string  $detail  Extra context for the user's message (see AiErrorCode::message()).
     */
    public static function error(AiErrorCode $code, ?string $detail = null): self
    {
        return new self('error', text: (string) $detail, errorCode: $code);
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
            'status' => ['phase' => $this->phase, 'label' => $this->text],
            'error' => ['code' => $this->errorCode->value, 'message' => $this->errorCode->message($this->text ?: null)],
            default => ['finish_reason' => $this->finishReason],
        });
    }
}
