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
    case UpstreamUnavailable = 'upstream_unavailable';
    case UpstreamUnauthorized = 'upstream_unauthorized';
    case UpstreamInterrupted = 'upstream_interrupted';
    case ConversationBusy = 'conversation_busy';
    case ConversationNotFound = 'conversation_not_found';
    case InputSensitive = 'input_sensitive';
    case InputInvalid = 'input_invalid';
    case FileInvalid = 'file_invalid';
    case FileTooLarge = 'file_too_large';
    case FileUnsupported = 'file_unsupported';
    case FileSensitive = 'file_sensitive';

    /**
     * Friendly message shown to the user in the chat box. For sensitive input, `$detail` is the AI service's own
     * explanation (e.g. "Input mengandung data sensitif (NIK/KTP)…") and replaces the generic text.
     */
    public function message(?string $detail = null): string
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
            self::UpstreamUnavailable => __('Layanan AI sedang tidak tersedia. Coba beberapa saat lagi.'),
            self::UpstreamUnauthorized => __('Akses ke layanan AI ditolak (konfigurasi API key). Hubungi admin.'),
            self::UpstreamInterrupted => __('Koneksi ke layanan AI terputus sebelum jawaban selesai. Coba lagi.'),
            self::ConversationBusy => __('Jawaban sebelumnya masih diproses. Tunggu sebentar lalu coba lagi.'),
            self::ConversationNotFound => __('Riwayat percakapan ini tidak ditemukan di layanan AI. Mulai percakapan baru.'),
            self::InputSensitive => $detail
                ?: __('Pesan ditolak karena memuat data sensitif (mis. NIK, NPWP, nomor rekening, kredensial). Hapus atau samarkan data tersebut lalu kirim ulang.'),
            self::InputInvalid => __('Pesan tidak valid sehingga ditolak oleh layanan AI.'),
            self::FileInvalid => __('File tidak dapat dibaca atau sudah tidak tersedia. Unggah ulang file Anda.'),
            self::FileTooLarge => __('File terlalu besar (maks :max MB).', ['max' => config('ai_center.files.max_mb')]),
            self::FileUnsupported => __('Jenis file tidak didukung.'),
            self::FileSensitive => __('File ditolak karena memuat data sensitif (mis. NIK, NPWP, nomor rekening, kredensial) atau wajah.'),
        };
    }

    /**
     * Whether the request was refused before the AI answered, so nothing is kept and the user's text goes back into the input box.
     */
    public function isRejectedBeforeSending(): bool
    {
        return in_array($this, [self::RateLimited, self::InputTooLong, self::FileInvalid, self::InputSensitive], true);
    }
}
