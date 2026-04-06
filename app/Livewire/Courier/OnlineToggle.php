<?php

namespace App\Livewire\Courier;

use App\Services\Courier\CourierPresenceService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class OnlineToggle extends Component
{
    public bool $online = false;
    public bool $busyWithActiveOrder = false;
    public ?string $activeOrderStatus = null;

    public function mount(): void
    {
        $this->syncOnlineState('mount');
    }

    public function hydrate(): void
    {
        $this->syncOnlineState('hydrate');
    }

    public function syncOnlineState(string $source = 'manual'): void
    {
        $service = $this->presenceService();
        $courier = $service->resolveAuthenticatedCourier();
        $runtime = $service->snapshot($courier);

        $this->online = (bool) ($runtime['online'] ?? false);
        $activeOrderStatus = $runtime['active_order_status'] ?? null;
        $this->activeOrderStatus = $activeOrderStatus;
        $this->busyWithActiveOrder = $activeOrderStatus !== null;

        if ($source === 'hydrate' && config('courier_runtime.incident_logging.enabled', false) && $courier) {
            Log::info('online_toggle_snapshot_after_hydrate', [
                'flow' => 'courier_presence',
                'courier_id' => $courier->id,
                'snapshot' => $runtime,
            ]);
        }
    }

    public function toggleOnlineState(): void
    {
        $service = $this->presenceService();
        $courier = $service->resolveAuthenticatedCourier();

        if (! $courier) {
            return;
        }

        $result = $service->toggleOnline($courier);

        $this->online = (bool) $result['online'];
        $this->activeOrderStatus = $result['after']['active_order_status'] ?? null;
        $this->busyWithActiveOrder = $this->activeOrderStatus !== null;

        $this->dispatch(
            'courier-online-toggled',
            online: $this->online,
            changed: (bool) $result['changed'],
            attempted_online: (bool) $result['attempted_online'],
            reason: $result['reason'],
            before: $result['before'],
            after: $result['after'],
        );

        if (! $result['changed']) {
            $this->dispatch(
                'courier-online-toggle-blocked',
                attempted_online: (bool) $result['attempted_online'],
                reason: $result['reason'],
                before: $result['before'],
                after: $result['after'],
            );

            return;
        }

        if ($this->online) {
            $this->dispatch('courier:online');

            return;
        }

        $this->dispatch('courier:offline');
    }

    public function render()
    {
        $this->syncOnlineState('render');

        return view('livewire.courier.online-toggle');
    }

    private function presenceService(): CourierPresenceService
    {
        return app(CourierPresenceService::class);
    }
}
