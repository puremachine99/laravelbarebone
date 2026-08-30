<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * The other participant in a direct (1:1) conversation.
     */
    public function otherParticipant(User $viewer): ?User
    {
        return $this->participants->firstWhere('id', '!=', $viewer->id);
    }

    /**
     * Find (or start) the direct conversation between these two users.
     */
    public static function findOrCreateDirect(User $a, User $b): self
    {
        $conversation = self::query()
            ->where('type', 'direct')
            ->whereHas('participants', fn ($query) => $query->where('users.id', $a->id))
            ->whereHas('participants', fn ($query) => $query->where('users.id', $b->id))
            ->first();

        if ($conversation) {
            return $conversation;
        }

        $conversation = self::create(['type' => 'direct']);
        $conversation->participants()->attach([$a->id, $b->id]);

        return $conversation;
    }
}
