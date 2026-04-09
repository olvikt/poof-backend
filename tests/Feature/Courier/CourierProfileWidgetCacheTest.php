<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Actions\Courier\Payout\CreateCourierWithdrawalRequestAction;
use App\Actions\Courier\Profile\PersistCourierAvatarAction;
use App\Actions\Courier\Profile\PersistCourierProfileAction;
use App\Actions\Courier\Verification\ApproveCourierVerificationRequestAction;
use App\Actions\Courier\Verification\RejectCourierVerificationRequestAction;
use App\Actions\Courier\Verification\SubmitCourierVerificationRequestAction;
use App\DTO\Avatar\AvatarUploadData;
use App\DTO\Courier\Profile\CourierProfileUpdateData;
use App\Models\CourierEarning;
use App\Models\CourierVerificationRequest;
use App\Models\CourierWithdrawalRequest;
use App\Models\User;
use App\Services\Courier\Earnings\CourierBalanceSummaryService;
use App\Services\Courier\Payout\CourierPayoutPolicyService;
use App\Services\Courier\Profile\CourierProfileReadModelService;
use App\Services\Courier\Rating\CourierRatingSummaryService;
use App\Services\Courier\Verification\CourierVerificationSummaryService;
use App\Support\Courier\Profile\CourierProfileWidgetCacheKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CourierProfileWidgetCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_cached_second_read_reuses_rating_and_balance_blocks_while_ttl_alive(): void
    {
        config()->set('courier_profile_cache.enabled', true);
        config()->set('courier_profile_cache.ttl_seconds.rating_summary', 300);
        config()->set('courier_profile_cache.ttl_seconds.balance_summary', 300);

        $courier = $this->createCourier();

        $rating = Mockery::mock(CourierRatingSummaryService::class);
        $rating->shouldReceive('forCourier')->once()->andReturn(['current_score' => 4.9]);
        $this->app->instance(CourierRatingSummaryService::class, $rating);

        $balance = Mockery::mock(CourierBalanceSummaryService::class);
        $balance->shouldReceive('forCourier')->once()->andReturn([
            'gross_earnings_total' => 1000,
            'courier_net_balance' => 700,
            'balance_formatted' => '700,00 ₴',
        ]);
        $this->app->instance(CourierBalanceSummaryService::class, $balance);

        $verification = Mockery::mock(CourierVerificationSummaryService::class);
        $verification->shouldReceive('forCourier')->twice()->andReturn(['status' => 'basic_profile_complete']);
        $this->app->instance(CourierVerificationSummaryService::class, $verification);

        $policy = Mockery::mock(CourierPayoutPolicyService::class);
        $policy->shouldReceive('summaryFor')->once()->andReturn([
            'can_request_withdrawal' => true,
            'min_withdrawal_amount' => 500,
            'withdrawal_block_reason' => null,
        ]);
        $this->app->instance(CourierPayoutPolicyService::class, $policy);

        $service = app(CourierProfileReadModelService::class);

        $first = $service->forCourier($courier);
        $second = $service->forCourier($courier);

        $this->assertSame(4.9, $first['rating_summary']['current_score']);
        $this->assertSame($first['rating_summary'], $second['rating_summary']);
        $this->assertSame($first['balance_summary'], $second['balance_summary']);
    }

    public function test_cache_failure_falls_back_to_direct_compute(): void
    {
        config()->set('courier_profile_cache.enabled', true);
        config()->set('courier_profile_cache.ttl_seconds.rating_summary', 300);

        Cache::shouldReceive('get')->andThrow(new \RuntimeException('cache offline'));
        Cache::shouldReceive('put')->never();

        $courier = $this->createCourier();

        $rating = Mockery::mock(CourierRatingSummaryService::class);
        $rating->shouldReceive('forCourier')->once()->andReturn(['current_score' => 4.4]);
        $this->app->instance(CourierRatingSummaryService::class, $rating);

        $balance = Mockery::mock(CourierBalanceSummaryService::class);
        $balance->shouldReceive('forCourier')->once()->andReturn([
            'gross_earnings_total' => 0,
            'courier_net_balance' => 0,
            'balance_formatted' => '0,00 ₴',
        ]);
        $this->app->instance(CourierBalanceSummaryService::class, $balance);

        $verification = Mockery::mock(CourierVerificationSummaryService::class);
        $verification->shouldReceive('forCourier')->once()->andReturn(['status' => 'basic_profile_complete']);
        $this->app->instance(CourierVerificationSummaryService::class, $verification);

        $policy = Mockery::mock(CourierPayoutPolicyService::class);
        $policy->shouldReceive('summaryFor')->once()->andReturn([
            'can_request_withdrawal' => false,
            'min_withdrawal_amount' => 500,
            'withdrawal_block_reason' => 'below_minimum',
        ]);
        $this->app->instance(CourierPayoutPolicyService::class, $policy);

        $summary = app(CourierProfileReadModelService::class)->forCourier($courier);

        $this->assertSame(4.4, $summary['rating_summary']['current_score']);
    }

    public function test_profile_update_invalidates_identity_related_blocks(): void
    {
        $courier = $this->createCourier();
        Cache::put(CourierProfileWidgetCacheKeys::forWidget($courier->id, CourierProfileWidgetCacheKeys::PROFILE_IDENTITY), ['a' => 1], 300);
        Cache::put(CourierProfileWidgetCacheKeys::forWidget($courier->id, CourierProfileWidgetCacheKeys::PROFILE_CONTACT), ['a' => 1], 300);
        Cache::put(CourierProfileWidgetCacheKeys::forWidget($courier->id, CourierProfileWidgetCacheKeys::PROFILE_ADDRESS), ['a' => 1], 300);

        app(PersistCourierProfileAction::class)->execute($courier, new CourierProfileUpdateData(
            name: 'Updated Courier',
            phone: '+380501234567',
            email: 'updated-cached@example.com',
            residenceAddress: 'Київ, вул. Тестова, 10',
        ));

        $this->assertNull(Cache::get(CourierProfileWidgetCacheKeys::forWidget($courier->id, CourierProfileWidgetCacheKeys::PROFILE_IDENTITY)));
        $this->assertNull(Cache::get(CourierProfileWidgetCacheKeys::forWidget($courier->id, CourierProfileWidgetCacheKeys::PROFILE_CONTACT)));
        $this->assertNull(Cache::get(CourierProfileWidgetCacheKeys::forWidget($courier->id, CourierProfileWidgetCacheKeys::PROFILE_ADDRESS)));
    }

    public function test_avatar_update_invalidates_profile_media_block(): void
    {
        Storage::fake('public');

        $courier = $this->createCourier();
        $mediaKey = CourierProfileWidgetCacheKeys::forWidget($courier->id, CourierProfileWidgetCacheKeys::PROFILE_MEDIA);
        Cache::put($mediaKey, ['avatar_url' => 'old'], 300);

        app(PersistCourierAvatarAction::class)->execute(
            $courier,
            new AvatarUploadData(UploadedFile::fake()->image('avatar.jpg')),
        );

        $this->assertNull(Cache::get($mediaKey));
    }

    public function test_withdrawal_request_invalidates_balance_summary_block(): void
    {
        config()->set('courier_payout.minimum_withdrawal_amount', 100);

        $courier = $this->createCourier();
        CourierEarning::query()->create([
            'courier_id' => $courier->id,
            'order_id' => null,
            'gross_amount' => 1000,
            'commission_rate_percent' => 0,
            'commission_amount' => 0,
            'net_amount' => 1000,
            'bonuses_amount' => 0,
            'penalties_amount' => 0,
            'adjustments_amount' => 0,
            'earning_status' => CourierEarning::STATUS_SETTLED,
            'settled_at' => now(),
        ]);

        $balanceKey = CourierProfileWidgetCacheKeys::forWidget($courier->id, CourierProfileWidgetCacheKeys::BALANCE_SUMMARY);
        Cache::put($balanceKey, ['cached' => true], 300);

        app(CreateCourierWithdrawalRequestAction::class)->execute($courier, 200, 'invalidate cache');

        $this->assertNull(Cache::get($balanceKey));
        $this->assertDatabaseHas('courier_withdrawal_requests', [
            'courier_id' => $courier->id,
            'amount' => 200,
            'status' => CourierWithdrawalRequest::STATUS_REQUESTED,
        ]);
    }

    public function test_runtime_snapshot_remains_exact_after_profile_cache_warmup(): void
    {
        $courier = $this->createCourier(['is_online' => false, 'session_state' => User::SESSION_OFFLINE]);

        $this->actingAs($courier, 'web')->get(route('courier.profile'))->assertOk();

        $courier->update([
            'is_online' => true,
            'session_state' => User::SESSION_ONLINE,
        ]);

        $this->actingAs($courier, 'web')
            ->getJson('/api/courier/runtime')
            ->assertOk()
            ->assertJsonPath('runtime.online', true);
    }

    public function test_profile_read_model_does_not_lookup_cache_for_verification_widget(): void
    {
        $courier = $this->createCourier();
        $verificationKey = CourierProfileWidgetCacheKeys::forWidget($courier->id, 'profile_verification');

        Cache::spy();

        app(CourierProfileReadModelService::class)->forCourier($courier);

        Cache::shouldNotHaveReceived('get', [$verificationKey]);
    }

    public function test_warmed_profile_cache_does_not_make_verification_widget_stale(): void
    {
        Storage::fake('local');

        $courier = $this->createCourier();

        $service = app(CourierProfileReadModelService::class);
        $before = $service->forCourier($courier);
        $this->assertSame('not_submitted', $before['profile_verification']['status']);

        app(SubmitCourierVerificationRequestAction::class)->execute(
            $courier,
            CourierVerificationRequest::DOCUMENT_TYPE_PASSPORT,
            UploadedFile::fake()->image('passport.jpg')->size(300),
        );

        $after = $service->forCourier($courier->fresh());
        $this->assertSame('pending_review', $after['profile_verification']['status']);
    }

    public function test_after_approve_next_profile_read_shows_verified_state_immediately(): void
    {
        $courier = $this->createCourier();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);

        $request = CourierVerificationRequest::factory()->create([
            'courier_id' => $courier->id,
            'status' => CourierVerificationRequest::STATUS_PENDING_REVIEW,
        ]);

        $service = app(CourierProfileReadModelService::class);
        $service->forCourier($courier);

        app(ApproveCourierVerificationRequestAction::class)->execute($request, $admin);

        $after = $service->forCourier($courier->fresh());
        $this->assertSame('verified', $after['profile_verification']['status']);
    }

    public function test_after_reject_next_profile_read_shows_rejected_state_and_retry_cta_immediately(): void
    {
        $courier = $this->createCourier();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);

        $request = CourierVerificationRequest::factory()->create([
            'courier_id' => $courier->id,
            'status' => CourierVerificationRequest::STATUS_PENDING_REVIEW,
        ]);

        $service = app(CourierProfileReadModelService::class);
        $service->forCourier($courier);

        app(RejectCourierVerificationRequestAction::class)->execute($request, $admin, 'Фото нечітке');

        $after = $service->forCourier($courier->fresh());
        $this->assertSame('rejected', $after['profile_verification']['status']);
        $this->assertTrue((bool) $after['profile_verification']['can_submit']);
        $this->assertSame('Надіслати повторно', $after['profile_verification']['cta_label']);
    }

    private function createCourier(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'name' => 'Cached Courier',
            'email' => 'cached@example.com',
            'phone' => '+380501112233',
            'residence_address' => 'Київ, вул. Кешована, 1',
        ], $overrides));
    }
}
