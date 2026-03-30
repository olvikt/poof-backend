<?php

namespace Tests\Unit\Release;

use PHPUnit\Framework\TestCase;

class RuntimeConfigStoreContractTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 3);
    }

    public function test_runtime_defaults_pin_redis_first_cache_and_queue_contract(): void
    {
        $cacheConfig = file_get_contents($this->repoRoot.'/config/cache.php');
        $queueConfig = file_get_contents($this->repoRoot.'/config/queue.php');
        $envExample = file_get_contents($this->repoRoot.'/.env.example');

        $this->assertNotFalse($cacheConfig);
        $this->assertNotFalse($queueConfig);
        $this->assertNotFalse($envExample);

        $this->assertStringContainsString("'default' => env('CACHE_STORE', 'redis')", $cacheConfig);
        $this->assertStringNotContainsString("env('CACHE_DRIVER'", $cacheConfig);
        $this->assertStringContainsString("'default' => env('QUEUE_CONNECTION', 'redis')", $queueConfig);
        $this->assertStringContainsString('CACHE_STORE=redis', $envExample);
        $this->assertStringContainsString('QUEUE_CONNECTION=redis', $envExample);
    }
}
