<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isImage(): bool
    {
        return $this->attachment_mime && str_starts_with($this->attachment_mime, 'image/');
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment_path ? Storage::disk('s3')->temporaryUrl($this->attachment_path, now()->addMinutes(15)) : null;
    }

    /**
     * URL of the compressed WebP shown inline in the thread. Falls back to the original
     * attachment when no preview was generated (e.g. GD couldn't decode the source image).
     */
    public function previewUrl(): ?string
    {
        if ($this->attachment_preview_path) {
            return Storage::disk('s3')->temporaryUrl($this->attachment_preview_path, now()->addMinutes(15));
        }

        return $this->attachmentUrl();
    }
}
