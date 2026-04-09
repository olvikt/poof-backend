<?php

declare(strict_types=1);

namespace App\Support\Courier\Observability;

use Illuminate\Support\Facades\Log;

class CourierRuntimeRequestCollector
{
    private int $runtimeSnapshotCalls = 0;
    private int $activeOrderReads = 0;
    private int $authenticatedCourierResolves = 0;
    private ?bool $hasActiveOrder = null;
    private ?bool $online = null;
    private ?string $status = null;

    public function reset(): void
    {
        $this->runtimeSnapshotCalls = 0;
        $this->activeOrderReads = 0;
        $this->authenticatedCourierResolves = 0;
        $this->hasActiveOrder = null;
        $this->online = null;
        $this->status = null;
    }

    public function incrementRuntimeSnapshotCalls(): void
    {
        $this->runtimeSnapshotCalls++;
    }

    public function incrementActiveOrderReads(): void
    {
        $this->activeOrderReads++;
    }

    public function incrementAuthenticatedCourierResolves(): void
    {
        $this->authenticatedCourierResolves++;
    }

    /**
     * @param  array<string,mixed>|null  $runtime
     */
    public function rememberRuntime(?array $runtime): void
    {
        if (! is_array($runtime)) {
            return;
        }

        if (array_key_exists('has_active_order', $runtime)) {
            $this->hasActiveOrder = (bool) $runtime['has_active_order'];
        }

        if (array_key_exists('online', $runtime)) {
            $this->online = (bool) $runtime['online'];
        }

        if (array_key_exists('status', $runtime)) {
            $this->status = (string) $runtime['status'];
        }
    }

    /**
     * @param  array<string,mixed>  $context
     */
    public function observeEndpoint(
        string $endpointName,
        string $surfaceType,
        float $startedAt,
        array $context = [],
    ): void {
        $payload = array_merge($context, [
            'endpoint_name' => $endpointName,
            'surface_type' => $surfaceType,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'runtime_snapshot_calls' => $this->runtimeSnapshotCalls,
            'active_order_reads' => $this->activeOrderReads,
            'authenticated_courier_resolves' => $this->authenticatedCourierResolves,
            'has_active_order' => $context['has_active_order'] ?? $this->hasActiveOrder,
            'online' => $context['online'] ?? $this->online,
            'status' => $context['status'] ?? $this->status,
            'counter' => 'courier_runtime_endpoint_observed_total',
            'counter_type' => 'request',
        ]);

        Log::info('courier_runtime_endpoint_observed', $payload);
    }
}
