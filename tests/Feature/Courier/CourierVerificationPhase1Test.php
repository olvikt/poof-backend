<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Actions\Courier\Verification\ApproveCourierVerificationRequestAction;
use App\Actions\Courier\Verification\RejectCourierVerificationRequestAction;
use App\Models\Courier;
use App\Models\CourierVerificationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CourierVerificationPhase1Test extends TestCase
{
    use RefreshDatabase;

    public function test_courier_can_submit_verification_document_from_profile_flow(): void
    {
        Storage::fake('local');
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web')
            ->post(route('courier.profile.verification.submit'), [
                'document_type' => CourierVerificationRequest::DOCUMENT_TYPE_PASSPORT,
                'document' => UploadedFile::fake()->image('passport.jpg')->size(400),
            ])
            ->assertSessionHasNoErrors();

        $request = CourierVerificationRequest::query()->firstOrFail();

        $this->assertSame(CourierVerificationRequest::STATUS_PENDING_REVIEW, $request->status);
        $this->assertNotSame('', $request->document_file_path);
        Storage::disk('local')->assertExists($request->document_file_path);
    }

    public function test_client_cannot_use_courier_verification_submit_route(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $this->actingAs($client, 'web')
            ->post(route('courier.profile.verification.submit'), [
                'document_type' => CourierVerificationRequest::DOCUMENT_TYPE_PASSPORT,
                'document' => UploadedFile::fake()->image('passport.jpg'),
            ])
            ->assertForbidden();
    }

    public function test_invalid_file_type_and_oversized_file_are_rejected_cleanly(): void
    {
        Storage::fake('local');
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web')
            ->post(route('courier.profile.verification.submit'), [
                'document_type' => CourierVerificationRequest::DOCUMENT_TYPE_PASSPORT,
                'document' => UploadedFile::fake()->create('document.pdf', 200, 'application/pdf'),
            ])
            ->assertSessionHasErrors('document');

        $this->actingAs($courier, 'web')
            ->post(route('courier.profile.verification.submit'), [
                'document_type' => CourierVerificationRequest::DOCUMENT_TYPE_ID_CARD,
                'document' => UploadedFile::fake()->image('big.jpg')->size(7000),
            ])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseCount('courier_verification_requests', 0);
    }

    public function test_rejected_request_can_be_resubmitted(): void
    {
        Storage::fake('local');
        $courier = $this->createCourier();

        CourierVerificationRequest::factory()->create([
            'courier_id' => $courier->id,
            'status' => CourierVerificationRequest::STATUS_REJECTED,
            'rejection_reason' => 'Фото розмите',
            'document_file_path' => 'courier-verification/old.jpg',
        ]);

        $this->actingAs($courier, 'web')
            ->post(route('courier.profile.verification.submit'), [
                'document_type' => CourierVerificationRequest::DOCUMENT_TYPE_ID_CARD,
                'document' => UploadedFile::fake()->image('id-card.jpg')->size(450),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('courier_verification_requests', 2);
        $this->assertDatabaseHas('courier_verification_requests', [
            'courier_id' => $courier->id,
            'status' => CourierVerificationRequest::STATUS_PENDING_REVIEW,
        ]);
    }

    public function test_double_first_submission_keeps_single_active_pending_request(): void
    {
        Storage::fake('local');
        $courier = $this->createCourier();

        $payload = [
            'document_type' => CourierVerificationRequest::DOCUMENT_TYPE_PASSPORT,
            'document' => UploadedFile::fake()->image('passport.jpg')->size(400),
        ];

        $this->actingAs($courier, 'web')
            ->post(route('courier.profile.verification.submit'), $payload)
            ->assertSessionHasNoErrors();

        $this->actingAs($courier, 'web')
            ->post(route('courier.profile.verification.submit'), [
                'document_type' => CourierVerificationRequest::DOCUMENT_TYPE_ID_CARD,
                'document' => UploadedFile::fake()->image('id-card.jpg')->size(350),
            ])
            ->assertSessionHasErrors('document');

        $this->assertSame(1, CourierVerificationRequest::query()->count());
        $this->assertSame(1, CourierVerificationRequest::query()
            ->where('courier_id', $courier->id)
            ->where('status', CourierVerificationRequest::STATUS_PENDING_REVIEW)
            ->count());
    }

    public function test_storage_write_failure_rolls_back_and_does_not_leave_pending_request(): void
    {
        $courier = $this->createCourier();
        config()->set('courier_verification.storage_disk', 'verification-fail');

        $diskMock = Mockery::mock();
        $diskMock->shouldReceive('putFileAs')->once()->andReturn(false);

        Storage::shouldReceive('disk')
            ->once()
            ->with('verification-fail')
            ->andReturn($diskMock);

        $this->actingAs($courier, 'web')
            ->post(route('courier.profile.verification.submit'), [
                'document_type' => CourierVerificationRequest::DOCUMENT_TYPE_PASSPORT,
                'document' => UploadedFile::fake()->image('passport.jpg')->size(400),
            ])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseCount('courier_verification_requests', 0);
    }

    public function test_admin_can_view_review_surface_and_non_admin_cannot(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $courier = $this->createCourier();

        CourierVerificationRequest::factory()->create([
            'courier_id' => $courier->id,
        ]);

        $this->actingAs($admin, 'web')
            ->get('/admin/courier-verification-requests')
            ->assertOk();

        $this->actingAs($courier, 'web')
            ->get('/admin/courier-verification-requests')
            ->assertForbidden();
    }

    public function test_admin_can_approve_and_reject_requests_with_projection_semantics(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $courier = $this->createCourier();

        $pending = CourierVerificationRequest::factory()->create([
            'courier_id' => $courier->id,
            'status' => CourierVerificationRequest::STATUS_PENDING_REVIEW,
            'reviewed_at' => null,
            'reviewed_by' => null,
            'rejection_reason' => null,
        ]);

        app(ApproveCourierVerificationRequestAction::class)->execute($pending, $admin);

        $this->assertDatabaseHas('courier_verification_requests', [
            'id' => $pending->id,
            'status' => CourierVerificationRequest::STATUS_VERIFIED,
            'reviewed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $courier->id,
            'is_verified' => true,
        ]);
        $this->assertDatabaseHas('couriers', [
            'user_id' => $courier->id,
            'is_verified' => true,
        ]);

        $pendingReject = CourierVerificationRequest::factory()->create([
            'courier_id' => $courier->id,
            'status' => CourierVerificationRequest::STATUS_PENDING_REVIEW,
        ]);

        app(RejectCourierVerificationRequestAction::class)->execute($pendingReject, $admin, 'Нечітке фото документа');

        $this->assertDatabaseHas('courier_verification_requests', [
            'id' => $pendingReject->id,
            'status' => CourierVerificationRequest::STATUS_REJECTED,
            'rejection_reason' => 'Нечітке фото документа',
            'reviewed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $courier->id,
            'is_verified' => false,
        ]);
    }

    public function test_profile_page_renders_status_from_verification_request_summary_not_stale_boolean_only(): void
    {
        $courier = $this->createCourier([
            'is_verified' => true,
        ]);

        CourierVerificationRequest::factory()->create([
            'courier_id' => $courier->id,
            'status' => CourierVerificationRequest::STATUS_REJECTED,
            'rejection_reason' => 'Потрібно нове фото документа',
        ]);

        $this->actingAs($courier, 'web')
            ->get(route('courier.profile'))
            ->assertOk()
            ->assertSee('Верифікацію відхилено')
            ->assertSee('Потрібно нове фото документа');
    }

    private function createCourier(array $overrides = []): User
    {
        $courier = User::factory()->create(array_merge([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'phone' => '+380500000001',
            'residence_address' => 'Київ, вул. Базова, 1',
            'is_verified' => false,
        ], $overrides));

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_OFFLINE,
            'is_verified' => false,
        ]);

        return $courier;
    }
}
