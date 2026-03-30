<?php

declare(strict_types=1);

namespace Tests\Unit\Release;

use Database\Seeders\BrowserE2eSeeder;
use PHPUnit\Framework\TestCase;

class BrowserE2eLaneWiringTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repoRoot = dirname(__DIR__, 3);
    }

    public function test_browser_e2e_seeder_class_is_autoloadable(): void
    {
        $this->assertTrue(class_exists(BrowserE2eSeeder::class));
    }

    public function test_browser_e2e_workflow_uses_non_fragile_short_seed_class_invocation(): void
    {
        $workflow = file_get_contents($this->repoRoot.'/.github/workflows/tests.yml');

        $this->assertNotFalse($workflow);
        $this->assertStringContainsString('php artisan migrate:fresh --force', $workflow);
        $this->assertStringContainsString('php artisan db:seed --class=BrowserE2eSeeder --force', $workflow);
        $this->assertStringNotContainsString('--seeder=Database\\Seeders\\BrowserE2eSeeder', $workflow);
    }
}
