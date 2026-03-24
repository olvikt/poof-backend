<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Tests\TestCase;

class OrderTestingFixtureBoundaryArchitectureTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function criticalFixtureFiles(): array
    {
        return [
            'tests/Feature/Api/OrderStoreTest.php',
            'tests/Feature/Api/ApiProtectedRoutesAuthTest.php',
            'tests/Feature/Courier/CourierRuntimeStateSyncTest.php',
        ];
    }

    private function normalized(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertIsString($contents);

        return preg_replace('/\s+/', ' ', $contents) ?? '';
    }

    public function test_critical_order_fixture_tests_use_canonical_state_builders_and_not_raw_create(): void
    {
        foreach ($this->criticalFixtureFiles() as $file) {
            $source = $this->normalized($file);

            $this->assertStringContainsString('BuildsOrderRuntimeFixtures', $source, $file);
            $this->assertStringContainsString('createDispatchableSearchingPaidOrder(', $source, $file);
            $this->assertStringNotContainsString('Order::query()->create(', $source, $file);
            $this->assertStringNotContainsString('Order::create(', $source, $file);
            $this->assertStringNotContainsString('Order::createForTesting(', $source, $file);
            $this->assertStringNotContainsString('Order::query()->forceCreate(', $source, $file);
            $this->assertStringNotContainsString('Order::unguarded(', $source, $file);
        }
    }
}
