<?php

return [
    /*
    |--------------------------------------------------------------------------
    | POD Onboarding Materials & Knowledge Base Configuration (UNA-021)
    |--------------------------------------------------------------------------
    |
    | Configuration for onboarding material uploads, private storage, allowed
    | file formats, and file-size constraints.
    |
    */

    // Storage disk for privately stored onboarding material files.
    // MUST be a private storage disk (e.g. 'local' or private cloud storage).
    // Publicly accessible disks (e.g. 'public' or disks with public visibility)
    // are strictly prohibited to ensure materials remain confidential.
    // Files are never exposed via public URLs; downloads are served through protected routes.
    'disk' => env('POD_MATERIALS_DISK', 'local'),

    // Directory inside the storage disk where POD material files are stored.
    'directory' => env('POD_MATERIALS_DIR', 'pod_materials'),

    // Maximum file size in kilobytes (default 20MB = 20480 KB).
    'max_file_size_kb' => (int) env('POD_MATERIALS_MAX_FILE_SIZE_KB', 20480),

    // Allowed file extensions.
    'allowed_extensions' => ['pdf', 'doc', 'docx'],

    // Allowed MIME types corresponding to allowed extensions.
    'allowed_mimes' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],

    /*
    |--------------------------------------------------------------------------
    | Asynchronous Text Extraction Configuration (UNA-022)
    |--------------------------------------------------------------------------
    |
    | Configuration for extracting searchable plain text asynchronously from
    | uploaded POD documents and materials (PDF, DOCX, DOC, TXT) using a
    | configurable AI provider and model (e.g. Gemini).
    |
    */
    'extraction' => [
        'enabled' => env('POD_MATERIALS_EXTRACTION_ENABLED', true),
        'provider' => env('POD_MATERIALS_AI_PROVIDER', 'gemini'),
        'queue' => env('POD_MATERIALS_EXTRACTION_QUEUE', 'default'),
        'timeout' => (int) env('POD_MATERIALS_EXTRACTION_TIMEOUT', 120),
        'tries' => (int) env('POD_MATERIALS_EXTRACTION_TRIES', 3),
        'backoff' => [15, 60, 180],
        'max_extracted_characters' => (int) env('POD_MATERIALS_MAX_EXTRACTED_CHARS', 500000),

        /*
        | Configured AI extraction providers. Each provider must implement
        | App\Contracts\DocumentTextExtractionProvider.
        */
        'providers' => [
            'gemini' => [
                'class' => \App\Services\PodDocument\Extraction\GeminiTextExtractionProvider::class,
                'api_key' => env('GEMINI_API_KEY'),
                'model' => env('GEMINI_EXTRACTION_MODEL', 'gemini-3.8-flash'),
                'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
                'timeout' => (int) env('GEMINI_TIMEOUT_SECONDS', 60),
                'temperature' => (float) env('GEMINI_TEMPERATURE', 0.0),
                'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 8192),
                'prompt' => env('GEMINI_EXTRACTION_PROMPT', 'Extract all readable text from this document verbatim. Return only the extracted plain text with no commentary, no summary, no conversational filler, and no markdown code fences.'),
            ],
        ],
    ],
];
