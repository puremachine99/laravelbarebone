<?php

namespace App\Models;

use Database\Factories\AiFileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file the Nakama AI service holds for a user: one they uploaded (`origin` "upload", waiting to be sent with a
 * message until `ai_message_id` is set) or one the AI generated in an answer (`origin` "generated"). Only the
 * service's file id is stored — the content is fetched from the service on download.
 */
class AiFile extends Model
{
    /** @use HasFactory<AiFileFactory> */
    use HasFactory;

    public const ORIGIN_UPLOAD = 'upload';

    public const ORIGIN_GENERATED = 'generated';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'ai_message_id');
    }

    /**
     * Uploads that were not attached to a message yet.
     *
     * @param  Builder<AiFile>  $query
     */
    public function scopePendingUpload(Builder $query): void
    {
        $query->where('origin', self::ORIGIN_UPLOAD)->whereNull('ai_message_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * "image" and "pdf" are previewed inline by the browser; anything else ("file") is only downloaded. Only raster
     * images count — an SVG could carry script.
     */
    public function previewKind(): string
    {
        return match (true) {
            in_array($this->mime_type, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true) => 'image',
            $this->mime_type === 'application/pdf' => 'pdf',
            default => 'file',
        };
    }

    /**
     * "272,3 KB" — formatted by hand because Laravel's Number helper needs the PHP intl extension.
     */
    public function sizeLabel(): ?string
    {
        if ($this->size_bytes === null) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size_bytes;

        for ($unit = 0; $size >= 1024 && $unit < count($units) - 1; $unit++) {
            $size /= 1024;
        }

        return ($unit === 0 ? (string) (int) $size : number_format($size, 1, ',', '.')).' '.$units[$unit];
    }

    /**
     * Pending uploads are not offered for download until they were sent with a message.
     */
    public function isDownloadable(): bool
    {
        return $this->ai_message_id !== null || $this->origin === self::ORIGIN_GENERATED;
    }

    /**
     * What the chat page needs to list a file and open its preview.
     *
     * @return array{id: string, name: string, size_bytes: int|null, size_label: string|null, is_image: bool, kind: string, preview_url: string, download_url: string|null}
     */
    public function previewPayload(): array
    {
        return [
            'id' => $this->remote_file_id,
            'name' => $this->name,
            'size_bytes' => $this->size_bytes,
            'size_label' => $this->sizeLabel(),
            'is_image' => $this->isImage(),
            'kind' => $this->previewKind(),
            'preview_url' => route('ai-center.files.preview', $this),
            'download_url' => $this->isDownloadable() ? route('ai-center.files.download', $this) : null,
        ];
    }
}
