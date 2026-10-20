<?php

namespace App\Enums;

/**
 * Standard error codes shared by Laravel and the AI service (see the AiGateway JSON contract).
 */
enum AiErrorCode: string
{
    case RateLimited = 'rate_limited';
    case InputTooLong = 'input_too_long';
    case UpstreamQuota = 'upstream_quota';
    case UpstreamTimeout = 'upstream_timeout';
    case UpstreamBlocked = 'upstream_blocked';
    case UpstreamError = 'upstream_error';
    case FileInvalid = 'file_invalid';
    case FileTooLarge = 'file_too_large';
    case FileUnsupported = 'file_unsupported';
    case FileSensitive = 'file_sensitive';

    /**
     * Friendly message shown to the user in the chat box.
     */
    public function message(): string
    {
        return match ($this) {
            self::RateLimited => __('Batas pemakaian harian Anda sudah tercapai. Coba lagi besok.'),
            self::InputTooLong => __('Pesan terlalu panjang (maks :max karakter).', [
                'max' => number_format(config('ai_center.limits.max_input_chars'), 0, ',', '.'),
            ]),
            self::UpstreamQuota => __('Kuota layanan AI sedang habis. Coba beberapa saat lagi.'),
            self::UpstreamTimeout => __('Layanan AI sedang lambat, coba lagi.'),
            self::UpstreamBlocked => __('Permintaan ini tidak dapat diproses oleh AI.'),
            self::UpstreamError => __('Terjadi gangguan pada layanan AI.'),
            self::FileInvalid => __('File tidak dapat dibaca atau sudah tidak tersedia. Unggah ulang file Anda.'),
            self::FileTooLarge => __('File terlalu besar (maks :max MB).', ['max' => config('ai_center.files.max_mb')]),
            self::FileUnsupported => __('Jenis file tidak didukung.'),
            self::FileSensitive => __('File ditolak karena memuat data sensitif (mis. NIK, NPWP, nomor rekening, kredensial) atau wajah.'),
        };
    }

    /**
     * Whether the request was refused before reaching the AI, so the user's text should go back into the input box.
     */
    public function isRejectedBeforeSending(): bool
    {
        return in_array($this, [self::RateLimited, self::InputTooLong, self::FileInvalid], true);
    }
}
