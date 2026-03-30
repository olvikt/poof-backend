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

    public function test_production_like_runtime_ignores_legacy_cache_driver_when_cache_store_is_unset(): void
    {
        $runtime = $this->resolveRuntimeStoresWithEnv([
            'APP_ENV=production',
            'CACHE_DRIVER=file',
            'QUEUE_CONNECTION=redis',
            'SESSION_DRIVER=redis',
            'DB_CONNECTION=sqlite',
        ], ['CACHE_STORE']);

        $this->assertSame('production', $runtime['app_env'] ?? null);
        $this->assertSame('redis', $runtime['cache_default'] ?? null);
        $this->assertSame('redis', $runtime['cache_store_driver'] ?? null);
        $this->assertSame('redis', $runtime['queue_default'] ?? null);
        $this->assertSame('redis', $runtime['session_driver'] ?? null);
        $this->assertSame('sqlite', $runtime['db_default'] ?? null);
    }

    /**
     * @param list<string> $env
     * @param list<string> $unset
     *
     * @return array<string, mixed>
     */
    private function resolveRuntimeStoresWithEnv(array $env, array $unset = []): array
    {
        $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode([
    'app_env' => (string) config('app.env'),
    'cache_default' => (string) config('cache.default'),
    'cache_store_driver' => (string) data_get(config('cache.stores'), config('cache.default').'.driver', ''),
    'queue_default' => (string) config('queue.default'),
    'session_driver' => (string) config('session.driver'),
    'db_default' => (string) config('database.default'),
], JSON_UNESCAPED_SLASHES);
PHP;

        $unsetArgs = array_map(
            static fn (string $name): string => '-u '.escapeshellarg($name),
            $unset
        );

        $command = sprintf(
            'cd %s && env %s %s %s -r %s',
            escapeshellarg($this->repoRoot),
            implode(' ', $unsetArgs),
            implode(' ', $env),
            escapeshellarg(PHP_BINARY),
            escapeshellarg($script)
        );

        exec('bash -lc '.escapeshellarg($command), $output, $exitCode);

        $this->assertSame(0, $exitCode, 'Failed to resolve runtime stores from Laravel bootstrap process.');

        $payload = implode("\n", $output);
        $decoded = json_decode($payload, true);

        $this->assertIsArray($decoded, 'Unexpected runtime payload: '.$payload);

        return $decoded;
    }
}
