<?php

declare(strict_types=1);

namespace App\Livewire\Client;

use App\Models\ClientSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Component;

class SubscriptionsPage extends Component
{
    public Collection $subscriptions;

    public array $stats = [
        'active' => 0,
        'paused' => 0,
        'completed' => 0,
        'renewals_soon' => 0,
        'total_paid' => 0,
    ];

    public function mount(): void
    {
        $this->reload();
    }

    public function pause(int $subscriptionId): void
    {
        $subscription = $this->findOwnSubscription($subscriptionId);

        if (! $subscription || $subscription->status !== ClientSubscription::STATUS_ACTIVE) {
            return;
        }

        $subscription->update([
            'status' => ClientSubscription::STATUS_PAUSED,
            'paused_at' => now(),
        ]);

        $this->reload();
    }

    public function resume(int $subscriptionId): void
    {
        $subscription = $this->findOwnSubscription($subscriptionId);

        if (! $subscription || $subscription->status !== ClientSubscription::STATUS_PAUSED) {
            return;
        }

        $subscription->update([
            'status' => ClientSubscription::STATUS_ACTIVE,
            'paused_at' => null,
        ]);

        $this->reload();
    }

    public function cancel(int $subscriptionId): void
    {
        $subscription = $this->findOwnSubscription($subscriptionId);

        if (! $subscription || $subscription->status === ClientSubscription::STATUS_CANCELLED) {
            return;
        }

        $subscription->update([
            'status' => ClientSubscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'auto_renew' => false,
        ]);

        $this->reload();
    }

    public function toggleAutoRenew(int $subscriptionId): void
    {
        $subscription = $this->findOwnSubscription($subscriptionId);

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'auto_renew' => ! (bool) $subscription->auto_renew,
        ]);

        $this->reload();
    }

    protected function reload(): void
    {
        $userId = (int) auth()->id();
        $now = CarbonImmutable::now();

        $this->subscriptions = ClientSubscription::query()
            ->where('client_id', $userId)
            ->with(['plan', 'address'])
            ->withSum(['generatedOrders as paid_amount' => fn ($query) => $query->where('payment_status', 'paid')], 'client_charge_amount')
            ->withSum(['generatedOrders as fallback_paid_amount' => fn ($query) => $query->where('payment_status', 'paid')], 'price')
            ->orderByDesc('created_at')
            ->get();

        $totalPaid = $this->subscriptions->sum(function (ClientSubscription $subscription): int {
            $clientChargeAmount = (int) ($subscription->paid_amount ?? 0);

            return $clientChargeAmount > 0
                ? $clientChargeAmount
                : (int) ($subscription->fallback_paid_amount ?? 0);
        });

        $this->stats = [
            'active' => $this->subscriptions->where('status', ClientSubscription::STATUS_ACTIVE)->count(),
            'paused' => $this->subscriptions->where('status', ClientSubscription::STATUS_PAUSED)->count(),
            'completed' => $this->subscriptions->filter(function (ClientSubscription $subscription) use ($now): bool {
                $ended = $subscription->ends_at !== null && $subscription->ends_at->lessThan($now);

                return $subscription->status === ClientSubscription::STATUS_CANCELLED || $ended;
            })->count(),
            'renewals_soon' => $this->subscriptions->filter(function (ClientSubscription $subscription) use ($now): bool {
                return $subscription->status === ClientSubscription::STATUS_ACTIVE
                    && $subscription->ends_at !== null
                    && $subscription->ends_at->between($now, $now->addDays(7));
            })->count(),
            'total_paid' => $totalPaid,
        ];
    }

    protected function findOwnSubscription(int $subscriptionId): ?ClientSubscription
    {
        return ClientSubscription::query()
            ->where('id', $subscriptionId)
            ->where('client_id', auth()->id())
            ->first();
    }

    public function render()
    {
        return view('livewire.client.subscriptions-page')
            ->layout('layouts.client');
    }
}
