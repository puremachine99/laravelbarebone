<?php

namespace App\Http\Controllers\AiCenter;

use App\Enums\AiErrorCode;
use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use App\Enums\AiUsageKind;
use App\Enums\AiUsageOutcome;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiFile;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AiCenter\AiChatRequest;
use App\Services\AiCenter\AiGateway;
use App\Services\AiCenter\AiStreamEvent;
use App\Services\AiCenter\AiUsageRecorder;
use App\Services\AiCenter\DailyQuota;
use App\Services\AiCenter\DataAssistant;
use Generator;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Sends one chat message to the AI and streams the answer back as Server-Sent Events:
 * `conversation` (the thread id, sent first), then `status` (what the AI is doing) and `delta`… and finally one `done` or `error`.
 *
 * Refusals (quota reached, message too long) are sent as an `error` event before anything is saved or the AI is called.
 *
 * Every request — answered, failed, cancelled or refused — also leaves one row in the AI usage log (no message text).
 */
class StreamMessageController extends Controller
{
    public function __invoke(Request $request, DailyQuota $quota): StreamedResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer'],
            'message' => ['required', 'string'],
            'quick_action' => ['nullable', 'string', Rule::in(array_keys(config('ai_center.quick_actions')))],
            'file_ids' => ['nullable', 'array', 'max:'.config('ai_center.files.max_per_message')],
            'file_ids.*' => ['string'],
        ]);

        $user = $request->user();

        $conversation = isset($validated['conversation_id'])
            ? $user->aiConversations()->findOrFail($validated['conversation_id'])
            : null;

        $text = trim($validated['message']);

        $fileIds = array_values(array_unique($validated['file_ids'] ?? []));
        $files = $user->aiFiles()->pendingUpload()->whereIn('remote_file_id', $fileIds)->get();

        $refusal = match (true) {
            mb_strlen($text) > config('ai_center.limits.max_input_chars') => AiErrorCode::InputTooLong,
            $files->count() !== count($fileIds) => AiErrorCode::FileInvalid,
            $quota->exceeded($user) => AiErrorCode::RateLimited,
            default => null,
        };

        if ($refusal) {
            app(AiUsageRecorder::class)->record($user, AiUsageKind::Chat, AiUsageOutcome::Denied, errorCode: $refusal);

            return response()->eventStream(fn () => yield AiStreamEvent::error($refusal)->toStreamedEvent(), endStreamWith: null);
        }

        $conversation ??= $user->aiConversations()->create();

        $history = $conversation->messages()
            ->where('status', AiMessageStatus::Complete)
            ->latest('id')
            ->take(config('ai_center.limits.history_window'))
            ->get()
            ->reverse()
            ->map(fn (AiMessage $message) => ['role' => $message->role->value, 'content' => $message->content])
            ->values()
            ->all();

        $userMessage = $conversation->messages()->create([
            'role' => AiMessageRole::User,
            'content' => $text,
            'status' => AiMessageStatus::Complete,
        ]);

        $files->each(fn (AiFile $file) => $file->update(['ai_message_id' => $userMessage->id]));

        $reply = $conversation->messages()->create([
            'role' => AiMessageRole::Assistant,
            'content' => '',
            'status' => AiMessageStatus::Streaming,
        ]);

        $conversation->update([
            'title' => $conversation->title ?? Str::limit(Str::squish($text), 60),
            'last_message_at' => now(),
        ]);

        $aiRequest = new AiChatRequest(
            requestId: (string) Str::uuid(),
            userId: $user->id,
            userName: $user->name,
            conversationId: $conversation->id,
            message: $text,
            history: $history,
            quickAction: $validated['quick_action'] ?? null,
            fileIds: $fileIds,
            remoteConversationId: $conversation->remote_conversation_id,
        );

        // Keep running after the browser hangs up (Stop button) so the reply is still saved as "cancelled".
        ignore_user_abort(true);
        set_time_limit(config('ai_center.timeouts.total') + 30);

        return response()->eventStream(fn () => $this->relay($user, $conversation, $aiRequest, $reply), endStreamWith: null);
    }

    /**
     * Forwards the gateway's events to the browser and always saves the reply's final text and status — the
     * `finally` also runs when the client disconnects, because the stream loop then abandons this generator.
     *
     * For users with data access, the DataAssistant first fetches the data the question needs (as that user) and the
     * answer gets a "data source" line appended.
     *
     * @return Generator<int, StreamedEvent>
     */
    private function relay(User $user, AiConversation $conversation, AiChatRequest $aiRequest, AiMessage $reply): Generator
    {
        $content = '';
        $status = AiMessageStatus::Cancelled;
        $errorCode = null;
        $sources = null;
        $doneEvent = null;
        $startedAt = hrtime(true);
        $firstTokenMs = null;
        $generatedFileTypes = [];

        yield new StreamedEvent('conversation', ['id' => $conversation->id]);

        try {
            $prepared = app(DataAssistant::class)->prepare($user, $aiRequest);

            if ($prepared) {
                $aiRequest = $prepared['request'];
                $sources = $prepared['sources'];
            }

            foreach (app(AiGateway::class)->stream($aiRequest) as $event) {
                if ($event->isStarted()) {
                    $conversation->update(['remote_conversation_id' => $event->remoteConversationId]);

                    continue;
                }

                if ($event->isStatus()) {
                    yield $event->toStreamedEvent();

                    continue;
                }

                if ($event->isFile()) {
                    $file = $user->aiFiles()->updateOrCreate(['remote_file_id' => $event->remoteFileId], [
                        'ai_message_id' => $reply->id,
                        'origin' => AiFile::ORIGIN_GENERATED,
                        'name' => $event->fileName,
                        'mime_type' => $event->fileMimeType,
                    ]);
                    $generatedFileTypes[] = AiUsageRecorder::fileType($event->fileName, $event->fileMimeType);

                    yield new StreamedEvent('file', $file->previewPayload() + ['url' => route('ai-center.files.download', $file)]);

                    continue;
                }

                if ($event->isDelta()) {
                    $firstTokenMs ??= AiUsageRecorder::elapsedMs($startedAt);
                    $content .= $event->text;
                } elseif ($event->isDone()) {
                    $status = AiMessageStatus::Complete;
                    $doneEvent = $event;

                    if ($sources !== null) {
                        $content .= $sources;

                        yield AiStreamEvent::delta($sources)->toStreamedEvent();
                    }
                } else {
                    $status = AiMessageStatus::Error;
                    $errorCode = $event->errorCode;

                    if ($errorCode->isRejectedBeforeSending()) {
                        $this->discardRefusedTurn($conversation, $reply);
                    }
                }

                yield $event->toStreamedEvent();

                if (! $event->isDelta()) {
                    break;
                }
            }
        } catch (Throwable $e) {
            report($e);

            $status = AiMessageStatus::Error;
            $errorCode = AiErrorCode::UpstreamError;

            yield AiStreamEvent::error($errorCode)->toStreamedEvent();
        } finally {
            $reply->update([
                'content' => $content,
                'status' => $status,
                'error_code' => $errorCode,
            ]);

            app(AiUsageRecorder::class)->record(
                $user,
                AiUsageKind::Chat,
                match (true) {
                    $errorCode?->isRejectedBeforeSending() === true => AiUsageOutcome::Denied,
                    $status === AiMessageStatus::Complete => AiUsageOutcome::Ok,
                    $status === AiMessageStatus::Error => AiUsageOutcome::Error,
                    default => AiUsageOutcome::Cancelled,
                },
                done: $doneEvent,
                errorCode: $errorCode,
                firstTokenMs: $firstTokenMs,
                durationMs: AiUsageRecorder::elapsedMs($startedAt),
                generatedFileTypes: $generatedFileTypes,
            );
        }
    }

    /**
     * The AI service refused the message itself (e.g. it contains a NIK): don't keep it — the browser puts the text
     * back into the input box to be fixed. Attached files go back to "pending" so they can be sent again.
     */
    private function discardRefusedTurn(AiConversation $conversation, AiMessage $reply): void
    {
        $userMessage = $conversation->messages()
            ->where('role', AiMessageRole::User)
            ->where('id', '<', $reply->id)
            ->latest('id')
            ->first();

        $userMessage?->files()->update(['ai_message_id' => null]);
        $userMessage?->delete();
        $reply->delete();

        if ($conversation->messages()->doesntExist()) {
            $conversation->delete();
        }
    }
}
