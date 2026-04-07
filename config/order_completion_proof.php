<?php

return [
    'auto_confirm_hours' => 24,
    'auto_confirm_batch_size' => 100,
    'signed_url_ttl_minutes' => 10,
    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
    ],
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'heic'],
    'max_file_size_bytes' => 10 * 1024 * 1024,
];
