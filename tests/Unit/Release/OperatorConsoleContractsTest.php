<?php

namespace Tests\Unit\Release;

use PHPUnit\Framework\TestCase;

class OperatorConsoleContractsTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 3);
    }

    public function test_console_routes_define_machine_friendly_scheduler_and_worker_contracts(): void
    {
        $consoleRoutes = file_get_contents($this->repoRoot.'/routes/console.php');

        $this->assertNotFalse($consoleRoutes);
        $this->assertStringContainsString("Cache::put(", $consoleRoutes);
        $this->assertStringContainsString("ops:contract:scheduler", $consoleRoutes);
        $this->assertStringContainsString("ops:contract:workers", $consoleRoutes);
        $this->assertStringContainsString("'status' => 'degraded'", $consoleRoutes);
        $this->assertStringContainsString("'status' => 'ok'", $consoleRoutes);
    }
}
