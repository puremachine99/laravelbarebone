<?php

/**
 * Nakama AI Center (Modul J) — internal Gemini chatbot.
 *
 * The AI never reads or writes ERP tables; it only sees what the user types (and, in phase 2, the PDF they upload).
 * Secrets (GEMINI_API_KEY, AI_SERVICE_TOKEN) live in .env only and must never reach the browser or the logs.
 *
 * @return array<string, mixed>
 */
return [
    /*
     * Which AiGateway implementation answers the chat: "fake" (canned replies for local dev & tests),
     * "nakama" (the Nakama LLM API), "gemini" (Google Gemini directly) or "http" (a separate AI service speaking the
     * same JSON contract).
     */
    'driver' => env('AI_CENTER_DRIVER', 'fake'),

    /*
     * Nakama LLM API (driver "nakama"). The API key is shared by every user of this app and is the only owner of the
     * conversations on the service — keep it in .env, never in the browser, logs or the repository.
     * All tools are off by default: the AI only sees what the user types (no web search, files, memory or documents).
     */
    'nakama' => [
        'url' => env('NAKAMA_AI_URL', 'https://nakama-ai.smartid.or.id'),
        'api_key' => env('NAKAMA_AI_API_KEY'),
        'provider' => env('NAKAMA_AI_PROVIDER', 'gemini'),
        'model' => env('NAKAMA_AI_MODEL'),
        'effort' => env('NAKAMA_AI_EFFORT', 'low'),
        'system_prompt' => 'Anda adalah asisten internal SmartID. Jawab dengan sopan, ringkas, dan dalam bahasa pengguna '
            .'(utamanya Indonesia). Akui bila tidak yakin dan jangan mengarang angka atau fakta. Anda tidak memiliki akses '
            .'ke data ERP dan tidak dapat mengubah data apa pun.',
        'tools' => [
            'web_search' => (bool) env('NAKAMA_AI_WEB_SEARCH', false),
            'web_fetch' => false,
            'image_generation' => false,
            'memory' => false,
            'document_generation' => false,
        ],
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

    'service' => [
        'url' => env('AI_SERVICE_URL'),
        'token' => env('AI_SERVICE_TOKEN'),
    ],

    'limits' => [
        'daily_messages' => (int) env('AI_DAILY_MESSAGE_LIMIT', 50),
        'per_minute' => (int) env('AI_PER_MINUTE_LIMIT', 10),
        'max_input_chars' => (int) env('AI_MAX_INPUT_CHARS', 8000),
        'history_window' => (int) env('AI_HISTORY_WINDOW', 20),
    ],

    /*
     * Seconds.
     */
    'timeouts' => [
        'connect' => (int) env('AI_TIMEOUT_CONNECT', 5),
        'first_token' => (int) env('AI_TIMEOUT_FIRST_TOKEN', 15),
        'total' => (int) env('AI_TIMEOUT_TOTAL', 120),
    ],

    /*
     * USD per 1M tokens, used to estimate cost in the usage log. Empty budget = no monthly cap.
     */
    'pricing' => [
        'input_per_1m' => env('AI_PRICE_INPUT_PER_1M'),
        'output_per_1m' => env('AI_PRICE_OUTPUT_PER_1M'),
    ],

    'monthly_budget_usd' => env('AI_MONTHLY_BUDGET_USD'),

    /*
     * Daily quotas reset at midnight in this timezone.
     */
    'timezone' => 'Asia/Jakarta',

    /*
     * Phase 2 — PDF uploads go to the existing Nakama bucket, one folder per user:
     * {folder}/{user_id}-{first-two-name-words}/{Y-m-d_His}_{original-name}.pdf
     */
    'pdf' => [
        'disk' => env('AI_PDF_DISK', 's3'),
        'folder' => env('AI_PDF_FOLDER', 'ai-center'),
        'max_mb' => (int) env('AI_PDF_MAX_MB', 10),
    ],

    /*
     * Shortcut chips under the chat box: picking one pre-fills the input with the template.
     *
     * @var array<string, array{label: string, description: string, icon: string, template: string}>
     */
    'quick_actions' => [
        'summarize' => [
            'label' => 'Ringkas teks',
            'description' => 'Ubah teks panjang jadi poin-poin penting.',
            'icon' => 'o-document-text',
            'template' => "Ringkas teks berikut menjadi poin-poin penting yang mudah dipahami:\n\n",
        ],
        'tidy_letter' => [
            'label' => 'Rapikan surat/laporan',
            'description' => 'Jadikan lebih formal, jelas, dan rapi.',
            'icon' => 'o-pencil-square',
            'template' => "Rapikan surat/laporan berikut agar lebih formal, jelas, dan sesuai kaidah bahasa Indonesia:\n\n",
        ],
        'draft_email' => [
            'label' => 'Buat draf email',
            'description' => 'Susun email profesional dari poin singkat.',
            'icon' => 'o-envelope',
            'template' => "Buatkan draf email profesional dengan konteks berikut:\n\nTujuan: \nPenerima: \nIsi pokok: ",
        ],
        'explain_term' => [
            'label' => 'Jelaskan istilah',
            'description' => 'Pahami istilah dengan bahasa sederhana.',
            'icon' => 'o-light-bulb',
            'template' => 'Jelaskan istilah berikut dengan bahasa sederhana beserta contohnya: ',
        ],
    ],
];
