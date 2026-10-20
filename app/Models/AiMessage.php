<?php

namespace App\Models;

use App\Enums\AiErrorCode;
use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use Database\Factories\AiMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiMessage extends Model
{
    /** @use HasFactory<AiMessageFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => AiMessageRole::class,
            'status' => AiMessageStatus::class,
            'error_code' => AiErrorCode::class,
            'feedback' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(AiFile::class);
    }

    public function isFromAssistant(): bool
    {
        return $this->role === AiMessageRole::Assistant;
    }

    /**
     * The AI's Markdown answer as HTML. Raw HTML in the answer is stripped and unsafe links (javascript:, data:)
     * are dropped, so a prompt-injected reply cannot run script in the viewer's browser.
     */
    public function renderedContent(): string
    {
        return Str::markdown($this->content, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
