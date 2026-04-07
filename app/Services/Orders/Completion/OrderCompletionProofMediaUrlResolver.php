<?php

declare(strict_types=1);

namespace App\Services\Orders\Completion;

use App\Models\OrderCompletionProof;
use Illuminate\Support\Facades\Storage;

class OrderCompletionProofMediaUrlResolver
{
    public function resolve(OrderCompletionProof $proof): ?string
    {
        $disk = $proof->file_disk ?: config('filesystems.default');
        $ttlMinutes = max(1, (int) config('order_completion_proof.signed_url_ttl_minutes', 10));

        try {
            return Storage::disk($disk)->temporaryUrl($proof->file_path, now()->addMinutes($ttlMinutes));
        } catch (\Throwable) {
            try {
                return Storage::disk($disk)->url($proof->file_path);
            } catch (\Throwable) {
                return null;
            }
        }
    }
}
