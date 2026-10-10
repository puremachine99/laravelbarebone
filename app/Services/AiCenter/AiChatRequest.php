<?php

namespace App\Services\AiCenter;

/**
 * What Laravel hands to the AiGateway for one chat turn (mirrors the JSON contract agreed with the AI service).
 */
final readonly class AiChatRequest
{
    public const PURPOSE_CHAT = 'chat';

    /**
     * A one-off call that only asks the AI which data queries to run (see DataAssistant); never shown to the user.
     */
    public const PURPOSE_DATA_PLAN = 'data_plan';

    /**
     * @param  array<int, array{role: string, content: string}>  $history  Oldest first, without the new message.
     * @param  array{url: string, mime: string, name: string, size_bytes: int}|null  $attachment
     * @param  string|null  $systemPrompt  Overrides config('ai_center.nakama.system_prompt') for this call.
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
        public ?string $systemPrompt = null,
        public string $purpose = self::PURPOSE_CHAT,
    ) {}

    /**
     * Same turn with a different message and system prompt (used to hand the AI the data it asked for).
     */
    public function withMessage(string $message, ?string $systemPrompt): self
    {
        return new self(
            requestId: $this->requestId,
            userId: $this->userId,
            userName: $this->userName,
            conversationId: $this->conversationId,
            message: $message,
            history: $this->history,
            attachment: $this->attachment,
            quickAction: $this->quickAction,
            remoteConversationId: $this->remoteConversationId,
            systemPrompt: $systemPrompt,
            purpose: $this->purpose,
        );
    }

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
