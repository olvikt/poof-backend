<?php

namespace App\Livewire\Courier;

use App\Services\Courier\CourierPresenceService;
use App\Services\Courier\Earnings\CourierBalanceSummaryService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class OnlineToggle extends Component
{
    public bool $online = false;
    public bool $busyWithActiveOrder = false;
    public ?string $activeOrderStatus = null;

    public bool $toggleInFlight = false;

    /**
     * @var array{completed_orders_count:int,gross_earnings_total:int,platform_commission_total:int,courier_net_balance:int,balance_formatted:string}
     */
    public array $balanceSummary = [
        'completed_orders_count' => 0,
        'gross_earnings_total' => 0,
        'platform_commission_total' => 0,
        'courier_net_balance' => 0,
        'balance_formatted' => '0,00 ₴',
    ];

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
        if ($this->toggleInFlight && in_array($source, ['hydrate', 'poll'], true)) {
            return;
        }

        $service = $this->presenceService();
        $courier = $service->resolveAuthenticatedCourier();
        $runtime = $service->snapshot($courier);

        $this->online = (bool) ($runtime['online'] ?? false);
        $activeOrderStatus = $runtime['active_order_status'] ?? null;
        $this->activeOrderStatus = $activeOrderStatus;
        $this->busyWithActiveOrder = $activeOrderStatus !== null;

        if ($courier) {
            $this->balanceSummary = $this->balanceSummaryService()->forCourier($courier);
        }

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
        if ($this->toggleInFlight) {
            return;
        }

        $this->toggleInFlight = true;

        try {
            $service = $this->presenceService();
            $courier = $service->resolveAuthenticatedCourier();

            if (! $courier) {
                return;
            }

            $result = $service->toggleOnline($courier);

            $this->online = (bool) $result['online'];
            $this->activeOrderStatus = $result['after']['active_order_status'] ?? null;
            $this->busyWithActiveOrder = $this->activeOrderStatus !== null;
            $this->balanceSummary = $this->balanceSummaryService()->forCourier($courier);

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
        } finally {
            $this->toggleInFlight = false;
        }
    }

    public function render()
    {
        return view('livewire.courier.online-toggle');
    }

    private function presenceService(): CourierPresenceService
    {
        return app(CourierPresenceService::class);
    }

    private function balanceSummaryService(): CourierBalanceSummaryService
    {
        return app(CourierBalanceSummaryService::class);
    }
}
