<?php

namespace App\Services\AiCenter;

use App\Enums\AiErrorCode;

/**
 * Stand-in "AI" for local development and tests: streams a canned Markdown reply word by word, without calling
 * Gemini. Type "/error <code>" (e.g. "/error upstream_quota") in the chat to preview an error state.
 */
class FakeAiGateway implements AiGateway
{
    /**
     * Every request this gateway received, so tests can assert whether the AI was called at all.
     *
     * @var array<int, AiChatRequest>
     */
    public array $requests = [];

    /**
     * Remote thread ids this gateway was asked to delete.
     *
     * @var array<int, string>
     */
    public array $forgotten = [];

    public function __construct(
        private int $delayMs = 0,
        private ?AiErrorCode $failWith = null,
    ) {}

    public function stream(AiChatRequest $request): iterable
    {
        $this->requests[] = $request;

        $failure = $this->failWith ?? $this->requestedFailure($request->message);

        if ($failure) {
            yield AiStreamEvent::error($failure);

            return;
        }

        foreach (preg_split('/(?<=\s)/', $this->reply($request)) as $word) {
            if ($this->delayMs > 0) {
                usleep($this->delayMs * 1000);
            }

            yield AiStreamEvent::delta($word);
        }

        $promptTokens = (int) ceil(mb_strlen($request->message) / 4);

        yield AiStreamEvent::done(
            usage: ['prompt_tokens' => $promptTokens, 'output_tokens' => 60, 'total_tokens' => $promptTokens + 60],
            model: 'fake',
            finishReason: 'STOP',
        );
    }

    public function forget(string $remoteConversationId): void
    {
        $this->forgotten[] = $remoteConversationId;
    }

    private function reply(AiChatRequest $request): string
    {
        $characters = number_format(mb_strlen($request->message), 0, ',', '.');
        $turn = intdiv(count($request->history), 2) + 1;

        return "**Mode uji — AI tiruan.** Pesan ke-{$turn} Anda ({$characters} karakter) sudah diterima.\n\n"
            ."Ini jawaban contoh untuk mencoba tampilan AI Center:\n\n"
            ."- Jawaban muncul sedikit demi sedikit, seperti sedang diketik\n"
            ."- Format **tebal**, *miring*, daftar, dan `kode` ikut ditampilkan\n"
            ."- Tombol salin dan 👍/👎 ada di bawah jawaban\n\n"
            .'Setel `AI_CENTER_DRIVER=gemini` di `.env` untuk memakai Gemini sungguhan.';
    }

    private function requestedFailure(string $message): ?AiErrorCode
    {
        if (! preg_match('/^\/error\s+(\w+)/', trim($message), $matches)) {
            return null;
        }

        return AiErrorCode::tryFrom($matches[1]) ?? AiErrorCode::UpstreamError;
    }
}
