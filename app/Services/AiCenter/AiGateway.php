<?php

namespace App\Services\AiCenter;

/**
 * The single seam between Nakama and whatever produces the AI answer (the Nakama LLM API, or the fake used in
 * tests). Implementations must never enable tools (web search, files, memory…) on the model and must never put
 * the API key in an exception message.
 */
interface AiGateway
{
    /**
     * Yields an optional `started` event (the AI service's own thread id), then `delta` events while the answer
     * streams in, then exactly one `done` or `error` event.
     *
     * @return iterable<AiStreamEvent>
     */
    public function stream(AiChatRequest $request): iterable;

    /**
     * Deletes the thread (and its messages) that the AI service keeps for a conversation. Never throws.
     */
    public function forget(string $remoteConversationId): void;
}
