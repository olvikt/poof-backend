<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Support\Orders\LegacyScheduleNormalizer;
use Tests\TestCase;

class LegacyScheduleNormalizerTest extends TestCase
{
    public function test_legacy_date_plus_time_is_combined_correctly(): void
    {
        $normalizer = app(LegacyScheduleNormalizer::class);

        [$from, $to] = $normalizer->resolveWindowFromLegacy('2026-04-07', '16:00', '18:00', 2);

        $this->assertNotNull($from);
        $this->assertNotNull($to);
        $this->assertSame('2026-04-07 16:00:00', $from?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-07 18:00:00', $to?->format('Y-m-d H:i:s'));
    }

    public function test_full_datetime_does_not_append_extra_time_again(): void
    {
        $normalizer = app(LegacyScheduleNormalizer::class);

        [$from, $to] = $normalizer->resolveWindowFromLegacy('2026-03-08 00:00:00', '16:00', '18:00', 2);

        $this->assertNotNull($from);
        $this->assertNotNull($to);
        $this->assertSame('2026-03-08 00:00:00', $from?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-08 02:00:00', $to?->format('Y-m-d H:i:s'));
    }

    public function test_malformed_legacy_values_return_null_window_instead_of_throwing(): void
    {
        $normalizer = app(LegacyScheduleNormalizer::class);

        [$from, $to] = $normalizer->resolveWindowFromLegacy('not-a-date', '16:00', null, 2);

        $this->assertNull($from);
        $this->assertNull($to);
    }

    public function test_datetime_in_time_field_is_respected(): void
    {
        $normalizer = app(LegacyScheduleNormalizer::class);

        [$from, $to] = $normalizer->resolveWindowFromLegacy('2026-04-07', '2026-04-08 10:00:00', null, 2);

        $this->assertSame('2026-04-08 10:00:00', $from?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-08 12:00:00', $to?->format('Y-m-d H:i:s'));
    }

    public function test_only_window_end_falls_back_to_null_window(): void
    {
        $normalizer = app(LegacyScheduleNormalizer::class);

        [$from, $to] = $normalizer->resolveWindowFromLegacy('2026-04-07', null, '18:00', 2);

        $this->assertNull($from);
        $this->assertNull($to);
    }
}
