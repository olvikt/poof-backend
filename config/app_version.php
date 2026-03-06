<?php

return [
    'version' => env('APP_VERSION') ?: (trim((string) @shell_exec('git describe --tags --abbrev=0 2>/dev/null')) ?: 'dev'),
];
