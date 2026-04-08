<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CourierVerificationRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourierVerificationRequest>
 */
class CourierVerificationRequestFactory extends Factory
{
    protected $model = CourierVerificationRequest::class;

    public function definition(): array
    {
        return [
            'courier_id' => User::factory()->state([
                'role' => User::ROLE_COURIER,
                'is_active' => true,
            ]),
            'document_type' => CourierVerificationRequest::DOCUMENT_TYPE_PASSPORT,
            'status' => CourierVerificationRequest::STATUS_PENDING_REVIEW,
            'document_file_path' => 'courier-verification/sample.jpg',
            'document_file_disk' => 'local',
            'document_mime_type' => 'image/jpeg',
            'document_file_size_bytes' => 1024,
            'submitted_at' => now(),
            'reviewed_at' => null,
            'reviewed_by' => null,
            'rejection_reason' => null,
        ];
    }
}
