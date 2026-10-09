<?php

namespace App\Services\AiCenter;

/**
 * What Laravel hands to the AiGateway for one chat turn (mirrors the JSON contract agreed with the AI service).
 */
final readonly class AiChatRequest
{
    /**
     * @param  array<int, array{role: string, content: string}>  $history  Oldest first, without the new message.
     * @param  array{url: string, mime: string, name: string, size_bytes: int}|null  $attachment
     */
    public function __construct(
        public string $requestId,
        public int $userId,
        public string $userName,
        public int $conversationId,
        public string $message,
        public array $history = [],
        public ?array $attachment = null,
        public ?string $quickAction = null,
        public ?string $remoteConversationId = null,
    ) {}

    /**
     * @return array{request_id: string, user: array{id: int, name: string}, conversation_id: int, message: string, history: array<int, array{role: string, content: string}>, attachment: array<string, mixed>|null, options: array{quick_action: string|null}}
     */
    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'user' => ['id' => $this->userId, 'name' => $this->userName],
            'conversation_id' => $this->conversationId,
            'message' => $this->message,
            'history' => $this->history,
            'attachment' => $this->attachment,
            'options' => ['quick_action' => $this->quickAction],
        ];
    }
}
