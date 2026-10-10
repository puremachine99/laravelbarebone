<?php

namespace App\Http\Controllers\AiCenter;

use App\Enums\AiErrorCode;
use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AiCenter\AiChatRequest;
use App\Services\AiCenter\AiGateway;
use App\Services\AiCenter\AiStreamEvent;
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
 * `conversation` (the thread id, sent first), then `delta`… and finally one `done` or `error`.
 *
 * Refusals (quota reached, message too long) are sent as an `error` event before anything is saved or the AI is called.
 */
class StreamMessageController extends Controller
{
    public function __invoke(Request $request, DailyQuota $quota): StreamedResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer'],
            'message' => ['required', 'string'],
            'quick_action' => ['nullable', 'string', Rule::in(array_keys(config('ai_center.quick_actions')))],
        ]);

        $user = $request->user();

        $conversation = isset($validated['conversation_id'])
            ? $user->aiConversations()->findOrFail($validated['conversation_id'])
            : null;

        $text = trim($validated['message']);

        $refusal = match (true) {
            mb_strlen($text) > config('ai_center.limits.max_input_chars') => AiErrorCode::InputTooLong,
            $quota->exceeded($user) => AiErrorCode::RateLimited,
            default => null,
        };

        if ($refusal) {
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

        $conversation->messages()->create([
            'role' => AiMessageRole::User,
            'content' => $text,
            'status' => AiMessageStatus::Complete,
        ]);

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

                if ($event->isDelta()) {
                    $content .= $event->text;
                } elseif ($event->isDone()) {
                    $status = AiMessageStatus::Complete;

                    if ($sources !== null) {
                        $content .= $sources;

                        yield AiStreamEvent::delta($sources)->toStreamedEvent();
                    }
                } else {
                    $status = AiMessageStatus::Error;
                    $errorCode = $event->errorCode;
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
        }
    }
}
