<?php

namespace App\Services\AiCenter;

use Illuminate\Http\UploadedFile;
use Psr\Http\Message\StreamInterface;

/**
 * The single seam between Nakama and whatever produces the AI answer (the Nakama LLM API, or the fake used in
 * tests). Implementations must only enable the tools switched on in config/ai_center.php and must never put
 * the API key in an exception message.
 */
interface AiGateway
{
    /**
     * Yields an optional `started` event (the AI service's own thread id), then `delta` events while the answer
     * streams in (and `file` events for documents or images it generated), then exactly one `done` or `error` event.
     *
     * @return iterable<AiStreamEvent>
     */
    public function stream(AiChatRequest $request): iterable;

    /**
     * Deletes the thread (and its messages) that the AI service keeps for a conversation. Never throws.
     */
    public function forget(string $remoteConversationId): void;

    /**
     * Uploads the user's files to the AI service so they can be referenced by id in a chat.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{id: string, name: string, mime_type: string, size_bytes: int|null}>
     *
     * @throws AiFileUploadException
     */
    public function uploadFiles(array $files): array;

    /**
     * Has the AI service build a document (xlsx, docx, pptx, csv, md) from structured data, without a chat.
     *
     * @param  array<string, mixed>  $document  `format`, `filename` and the sheets/sections/slides of the document
     * @return array{id: string, name: string, mime_type: string, size_bytes: int|null}
     *
     * @throws AiFileUploadException
     */
    public function generateDocument(array $document): array;

    /**
     * The content of a file the AI service holds, or null when it is gone. Never throws.
     */
    public function downloadFile(string $remoteFileId): ?StreamInterface;

    /**
     * Deletes a file on the AI service. Never throws.
     */
    public function deleteFile(string $remoteFileId): void;
}
