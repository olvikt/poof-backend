<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourierVerificationRequest;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourierVerificationDocumentPreviewController extends Controller
{
    public function __invoke(CourierVerificationRequest $verificationRequest): StreamedResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $disk = $verificationRequest->document_file_disk ?: config('courier_verification.storage_disk', 'local');

        abort_unless(Storage::disk($disk)->exists($verificationRequest->document_file_path), 404);

        return Storage::disk($disk)->response(
            $verificationRequest->document_file_path,
            basename($verificationRequest->document_file_path),
            [
                'Content-Type' => $verificationRequest->document_mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.basename($verificationRequest->document_file_path).'"',
            ],
        );
    }
}
