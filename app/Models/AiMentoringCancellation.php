<?php

namespace App\Models;

use Database\Factories\AiMentoringCancellationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A "Pendampingan" session the user cancelled. The Nakama mapping API cannot cancel or delete a session, so the
 * cancellation lives here: the session is hidden from the user's history, can no longer be answered, and must be
 * left out of the Monday/Friday reports. The session itself stays on the Nakama service.
 */
class AiMentoringCancellation extends Model
{
    /** @use HasFactory<AiMentoringCancellationFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
