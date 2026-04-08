<?php

declare(strict_types=1);

namespace App\Actions\Courier\Verification;

use App\Models\CourierVerificationRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubmitCourierVerificationRequestAction
{
    public function execute(User $courier, string $documentType, UploadedFile $document): CourierVerificationRequest
    {
        if (! $courier->isCourier()) {
            throw ValidationException::withMessages([
                'document' => 'Верифікація доступна лише курʼєрам.',
            ]);
        }

        if (! in_array($documentType, CourierVerificationRequest::allowedDocumentTypes(), true)) {
            throw ValidationException::withMessages([
                'document_type' => 'Непідтримуваний тип документа.',
            ]);
        }

        return DB::transaction(function () use ($courier, $documentType, $document): CourierVerificationRequest {
            User::query()
                ->whereKey($courier->id)
                ->lockForUpdate()
                ->first();

            $pendingExists = CourierVerificationRequest::query()
                ->where('courier_id', $courier->id)
                ->where('status', CourierVerificationRequest::STATUS_PENDING_REVIEW)
                ->lockForUpdate()
                ->exists();

            if ($pendingExists) {
                throw ValidationException::withMessages([
                    'document' => 'Документи вже на перевірці. Дочекайтеся рішення адміністратора.',
                ]);
            }

            $latest = CourierVerificationRequest::query()
                ->where('courier_id', $courier->id)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($latest?->status === CourierVerificationRequest::STATUS_VERIFIED) {
                throw ValidationException::withMessages([
                    'document' => 'Ваш профіль вже верифіковано.',
                ]);
            }

            $request = CourierVerificationRequest::query()->create([
                'courier_id' => $courier->id,
                'document_type' => $documentType,
                'status' => CourierVerificationRequest::STATUS_PENDING_REVIEW,
                'document_file_path' => '',
                'document_file_disk' => (string) config('courier_verification.storage_disk', 'local'),
                'document_mime_type' => (string) $document->getMimeType(),
                'document_file_size_bytes' => (int) $document->getSize(),
                'submitted_at' => now(),
            ]);

            $extension = strtolower($document->getClientOriginalExtension() ?: $document->extension() ?: 'jpg');
            $path = sprintf(
                'courier-verification/%d/%s/%d-document.%s',
                $courier->id,
                now()->format('Y/m/d'),
                $request->id,
                preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'jpg',
            );

            $stored = Storage::disk($request->document_file_disk ?: 'local')->putFileAs(
                dirname($path),
                $document,
                basename($path),
            );

            if ($stored === false) {
                throw ValidationException::withMessages([
                    'document' => 'Не вдалося зберегти документ. Спробуйте ще раз.',
                ]);
            }

            $request->update([
                'document_file_path' => $stored,
            ]);

            $courier->forceFill(['is_verified' => false])->save();
            $courier->courierProfile()?->update(['is_verified' => false]);

            return $request->fresh();
        });
    }
}
