<?php

namespace Tests\Unit\Domain\Address;

use App\Domain\Address\CoordinateTrustPolicy;
use App\Domain\Address\MarkerSyncContract;
use App\Domain\Address\Precision;
use PHPUnit\Framework\TestCase;

class MarkerSyncContractTest extends TestCase
{
    public function test_it_allows_only_known_incoming_sources(): void
    {
        $contract = new MarkerSyncContract();

        $this->assertTrue($contract->shouldAcceptIncomingSource('map'));
        $this->assertTrue($contract->shouldAcceptIncomingSource('geolocation'));
        $this->assertFalse($contract->shouldAcceptIncomingSource('sync'));
    }

    public function test_it_assigns_expected_precision_for_marker_sources(): void
    {
        $contract = new MarkerSyncContract();
        $policy = new CoordinateTrustPolicy();

        $this->assertSame(Precision::Exact, $contract->precisionForIncomingSource(50.45, 30.52, 'map', $policy));
        $this->assertSame(Precision::Approx, $contract->precisionForIncomingSource(50.45, 30.52, 'user', $policy));
    }
}
