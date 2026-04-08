<?php

declare(strict_types=1);

namespace App\Services\Courier\Verification;

use App\Models\CourierVerificationRequest;
use App\Models\User;

class CourierVerificationSummaryService
{
    public function forCourier(User $courier): array
    {
        $latest = $courier->latestCourierVerificationRequest()->first();

        if (! $latest) {
            return [
                'status' => 'not_submitted',
                'status_label' => 'Не верифіковано',
                'description' => 'Завантажте паспорт або ID-картку для перевірки.',
                'can_submit' => true,
                'show_rejection_reason' => false,
                'rejection_reason' => null,
                'cta_label' => 'Завантажити документ',
            ];
        }

        return match ($latest->status) {
            CourierVerificationRequest::STATUS_PENDING_REVIEW => [
                'status' => 'pending_review',
                'status_label' => 'Документи на перевірці',
                'description' => 'Адміністратор розгляне заявку найближчим часом.',
                'can_submit' => false,
                'show_rejection_reason' => false,
                'rejection_reason' => null,
                'cta_label' => null,
            ],
            CourierVerificationRequest::STATUS_VERIFIED => [
                'status' => 'verified',
                'status_label' => 'Верифіковано',
                'description' => 'Ваш акаунт успішно пройшов перевірку.',
                'can_submit' => false,
                'show_rejection_reason' => false,
                'rejection_reason' => null,
                'cta_label' => null,
            ],
            CourierVerificationRequest::STATUS_REJECTED => [
                'status' => 'rejected',
                'status_label' => 'Верифікацію відхилено',
                'description' => 'Виправте дані та відправте документ повторно.',
                'can_submit' => true,
                'show_rejection_reason' => true,
                'rejection_reason' => $latest->rejection_reason,
                'cta_label' => 'Надіслати повторно',
            ],
            default => [
                'status' => 'not_submitted',
                'status_label' => 'Не верифіковано',
                'description' => 'Завантажте паспорт або ID-картку для перевірки.',
                'can_submit' => true,
                'show_rejection_reason' => false,
                'rejection_reason' => null,
                'cta_label' => 'Завантажити документ',
            ],
        };
    }
}
