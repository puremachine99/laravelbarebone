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
     * @param  array{department: string|null, roles: array<int, string>, available_tools: array<int, string>, assertion: string|null}|null  $userContext  What the AI service needs to call the data API as tools for this user (gateway mode).
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
        public ?array $userContext = null,
    ) {}

    /**
     * Same turn with a different message and system prompt (used to hand the AI the data it asked for).
     */
    public function withMessage(string $message, ?string $systemPrompt): self
    {
        return $this->copy(['message' => $message, 'systemPrompt' => $systemPrompt]);
    }

    /**
     * Same turn with the user context for the AI service's data tools (gateway mode).
     *
     * @param  array{department: string|null, roles: array<int, string>, available_tools: array<int, string>, assertion: string|null}  $userContext
     */
    public function withUserContext(array $userContext, ?string $systemPrompt): self
    {
        return $this->copy(['userContext' => $userContext, 'systemPrompt' => $systemPrompt]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function copy(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
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
