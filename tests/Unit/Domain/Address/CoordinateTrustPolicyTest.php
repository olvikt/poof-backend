<?php

namespace Tests\Unit\Domain\Address;

use App\Domain\Address\CoordinateTrustPolicy;
use App\Domain\Address\Precision;
use PHPUnit\Framework\TestCase;

class CoordinateTrustPolicyTest extends TestCase
{
    public function test_it_blocks_field_geocode_for_exact_point(): void
    {
        $policy = new CoordinateTrustPolicy();

        $this->assertFalse($policy->shouldAcceptFieldGeocode(Precision::Exact));
        $this->assertTrue($policy->shouldAcceptFieldGeocode(Precision::Approx));
    }

    public function test_it_ignores_stale_geolocation_when_point_is_locked_or_exact(): void
    {
        $policy = new CoordinateTrustPolicy();

        $this->assertTrue($policy->shouldIgnoreIncomingCoords(50.45, 30.52, Precision::Exact, false, 50.5, 30.6, 'geolocation'));
        $this->assertTrue($policy->shouldIgnoreIncomingCoords(50.45, 30.52, Precision::Approx, true, 50.5, 30.6, 'geolocation'));
        $this->assertFalse($policy->shouldIgnoreIncomingCoords(50.45, 30.52, Precision::Approx, false, 50.5, 30.6, 'map'));
    }
}
