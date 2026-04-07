<?php

declare(strict_types=1);

namespace App\Services\Orders\Completion;

class OrderCompletionProofUploadValidator
{
    public function isValid(string $filePath, ?string $mimeType = null, ?int $fileSizeBytes = null): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $allowedExtensions = (array) config('order_completion_proof.allowed_extensions', []);
        if ($extension === '' || ! in_array($extension, $allowedExtensions, true)) {
            return false;
        }

        if ($mimeType !== null) {
            $allowedMimeTypes = (array) config('order_completion_proof.allowed_mime_types', []);
            if (! in_array($mimeType, $allowedMimeTypes, true)) {
                return false;
            }
        }

        if ($fileSizeBytes !== null) {
            $maxSize = (int) config('order_completion_proof.max_file_size_bytes', 0);
            if ($fileSizeBytes <= 0 || ($maxSize > 0 && $fileSizeBytes > $maxSize)) {
                return false;
            }
        }

        return true;
    }
}
