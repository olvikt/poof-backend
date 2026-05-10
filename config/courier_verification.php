<?php

declare(strict_types=1);

return [
    'storage_disk' => env('COURIER_VERIFICATION_STORAGE_DISK', 'local'),
    'max_file_size_bytes' => (int) env('COURIER_VERIFICATION_MAX_FILE_SIZE_BYTES', 20 * 1024 * 1024),
    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
    ],
];
