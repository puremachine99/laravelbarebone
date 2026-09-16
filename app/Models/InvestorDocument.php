<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class InvestorDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): ?string
    {
        return Storage::disk('s3')->temporaryUrl($this->file_path, now()->addMinutes(15), [
            'ResponseContentDisposition' => 'attachment; filename="'.$this->file_name.'"',
        ]);
    }

    // Buat modal preview — dipaksa "inline" biar browser nampilin filenya langsung, bukan malah ke-download.
    public function inlineUrl(): ?string
    {
        return Storage::disk('s3')->temporaryUrl($this->file_path, now()->addMinutes(15), [
            'ResponseContentDisposition' => 'inline; filename="'.$this->file_name.'"',
        ]);
    }

    // PDF & gambar bisa ditampilin langsung di browser (iframe/img); tipe lain (DOC/XLS/PPT dst) gak bisa
    // dirender inline tanpa library tambahan, jadi tombol Preview-nya di-disable buat tipe-tipe itu.
    public function isPreviewable(): bool
    {
        return in_array(strtolower($this->extension), ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    // Thumbnail kompresi WebP (cuma ada buat upload bertipe gambar — lihat ImagePreviewGenerator).
    public function previewUrl(): ?string
    {
        if (! $this->preview_path) {
            return null;
        }

        return Storage::disk('s3')->temporaryUrl($this->preview_path, now()->addMinutes(15));
    }

    public function humanSize(): string
    {
        $bytes = $this->size;

        return match (true) {
            $bytes >= 1_048_576 => round($bytes / 1_048_576, 1).' MB',
            $bytes >= 1_024 => round($bytes / 1_024, 1).' KB',
            default => $bytes.' B',
        };
    }

    // Warna badge tipe file di grid — biar sekilas kebedain tanpa perlu ikon.
    public function badgeClasses(): string
    {
        return match (strtolower($this->extension)) {
            'pdf' => 'badge-error',
            'doc', 'docx' => 'badge-info',
            'xls', 'xlsx' => 'badge-success',
            'ppt', 'pptx' => 'badge-warning',
            'jpg', 'jpeg', 'png', 'gif', 'webp' => 'bg-pink-500/15 text-pink-600 border-pink-500/20',
            default => 'badge-neutral',
        };
    }

    // Warna kotak placeholder (dipakai di grid kalau dokumennya gak punya thumbnail gambar) — sama
    // skema warnanya kayak badgeClasses(), cuma versi soft-background buat kotak yang lebih gede.
    public function placeholderClasses(): string
    {
        return match (strtolower($this->extension)) {
            'pdf' => 'bg-error/10 text-error',
            'doc', 'docx' => 'bg-info/10 text-info',
            'xls', 'xlsx' => 'bg-success/10 text-success',
            'ppt', 'pptx' => 'bg-warning/10 text-warning',
            'jpg', 'jpeg', 'png', 'gif', 'webp' => 'bg-pink-500/10 text-pink-600',
            default => 'bg-base-300 text-base-content/60',
        };
    }
}
