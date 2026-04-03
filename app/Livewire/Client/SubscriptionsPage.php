<?php

declare(strict_types=1);

namespace App\Livewire\Client;

use App\Models\ClientSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Component;

class SubscriptionsPage extends Component
{
    public bool $embedded = false;

    public Collection $subscriptions;

    public array $stats = [
        'active' => 0,
        'paused' => 0,
        'completed' => 0,
        'renewals_soon' => 0,
        'total_paid' => 0,
    ];

    public bool $showDetailsModal = false;
    public ?int $detailsSubscriptionId = null;
    public array $details = [];

    public function mount(bool $embedded = false): void
    {
        $this->embedded = $embedded;
        $this->reload();
    }

    public function pause(int $subscriptionId): void
    {
        $subscription = $this->findOwnSubscription($subscriptionId);

        if (! $subscription || ! $subscription->canPause()) {
            $this->dispatch('notify', type: 'error', message: 'Підписку можна поставити на паузу лише в активному стані.');
            return;
        }

        $subscription->forceFill([
            'status' => ClientSubscription::STATUS_PAUSED,
            'paused_at' => now(),
        ])->save();

        $this->dispatch('notify', type: 'success', message: 'Підписку поставлено на паузу.');

        $this->reload();
    }

    public function resume(int $subscriptionId): void
    {
        $subscription = $this->findOwnSubscription($subscriptionId);

        if (! $subscription || ! $subscription->canResume()) {
            $this->dispatch('notify', type: 'error', message: 'Відновити можна лише підписку в статусі «На паузі».');
            return;
        }

        $subscription->forceFill([
            'status' => ClientSubscription::STATUS_ACTIVE,
            'paused_at' => null,
        ])->save();

        $this->dispatch('notify', type: 'success', message: 'Підписку відновлено.');

        $this->reload();
    }

    public function cancel(int $subscriptionId): void
    {
        $subscription = $this->findOwnSubscription($subscriptionId);

        if (! $subscription || ! $subscription->canCancel()) {
            $this->dispatch('notify', type: 'error', message: 'Цю підписку вже не можна зупинити.');
            return;
        }

        $subscription->forceFill([
            'status' => ClientSubscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'auto_renew' => false,
        ])->save();

        $this->dispatch('notify', type: 'success', message: 'Підписку зупинено.');

        $this->reload();
    }

    public function toggleAutoRenew(int $subscriptionId): void
    {
        $subscription = $this->findOwnSubscription($subscriptionId);

        if (! $subscription || ! $subscription->canToggleAutoRenew()) {
            $this->dispatch('notify', type: 'error', message: 'Автопродовження доступне лише після першої оплати.');
            return;
        }

        $subscription->forceFill([
            'auto_renew' => ! (bool) $subscription->auto_renew,
        ])->save();

        $this->reload();
    }

    public function openDetails(int $subscriptionId): void
    {
        $subscription = $this->findOwnSubscription($subscriptionId);

        if (! $subscription || ! $subscription->canOpenDetails()) {
            return;
        }

        $this->detailsSubscriptionId = $subscriptionId;
        $this->details = $this->buildDetailsPayload($subscription);
        $this->showDetailsModal = true;
    }

    protected function reload(): void
    {
        $userId = (int) auth()->id();
        $now = CarbonImmutable::now();

        $this->subscriptions = ClientSubscription::query()
            ->where('client_id', $userId)
            ->with(['plan', 'address'])
            ->withCount(['generatedOrders as paid_orders_count' => fn ($query) => $query->where('payment_status', 'paid')])
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
            'active' => $this->subscriptions->filter(fn (ClientSubscription $subscription): bool => $subscription->display_status === ClientSubscription::STATUS_ACTIVE)->count(),
            'paused' => $this->subscriptions->filter(fn (ClientSubscription $subscription): bool => $subscription->display_status === ClientSubscription::STATUS_PAUSED)->count(),
            'completed' => $this->subscriptions->filter(function (ClientSubscription $subscription): bool {
                return in_array($subscription->display_status, [ClientSubscription::STATUS_CANCELLED, ClientSubscription::STATUS_COMPLETED], true);
            })->count(),
            'renewals_soon' => $this->subscriptions->filter(function (ClientSubscription $subscription) use ($now): bool {
                return $subscription->display_status === ClientSubscription::STATUS_ACTIVE
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
            ->with(['plan', 'address', 'generatedOrders' => fn ($query) => $query->orderBy('scheduled_date')])
            ->first();
    }

    protected function buildDetailsPayload(ClientSubscription $subscription): array
    {
        $planRuns = max(1, (int) ($subscription->plan?->pickups_per_month ?? 0));
        $orders = $subscription->generatedOrders
            ->sortBy('scheduled_date')
            ->values();
        $completedRuns = $orders->where('status', \App\Models\Order::STATUS_DONE)->count();
        $remainingRuns = max(0, $planRuns - $completedRuns);
        $nextPlanned = $orders
            ->whereIn('status', [\App\Models\Order::STATUS_NEW, \App\Models\Order::STATUS_SEARCHING, \App\Models\Order::STATUS_ACCEPTED, \App\Models\Order::STATUS_IN_PROGRESS])
            ->sortBy('scheduled_date')
            ->first();

        $timeline = $orders->map(function (\App\Models\Order $order): array {
            return [
                'date' => $order->scheduled_date?->format('d.m') ?? optional($order->created_at)->format('d.m') ?? '—',
                'completed' => $order->status === \App\Models\Order::STATUS_DONE,
                'status' => \App\Models\Order::STATUS_LABELS[$order->status] ?? $order->status,
            ];
        })->all();

        if (count($timeline) < $planRuns) {
            $remaining = $planRuns - count($timeline);

            for ($index = 0; $index < $remaining; $index++) {
                $timeline[] = [
                    'date' => '—',
                    'completed' => false,
                    'status' => $subscription->display_status === ClientSubscription::STATUS_PAUSED ? 'На паузі' : 'Очікується',
                ];
            }
        }

        $history = $orders
            ->map(fn (\App\Models\Order $order): array => [
                'id' => (int) $order->id,
                'status' => \App\Models\Order::STATUS_LABELS[$order->status] ?? $order->status,
                'date' => $order->scheduled_date?->format('d.m.Y') ?? optional($order->created_at)->format('d.m.Y') ?? '—',
                'is_subscription_origin' => $order->origin === \App\Models\Order::ORIGIN_SUBSCRIPTION,
            ])
            ->all();

        return [
            'plan_name' => (string) ($subscription->plan?->name ?? 'План підписки'),
            'period_start' => $subscription->startsAtForDisplay()?->format('d.m.Y') ?? '—',
            'period_end' => $subscription->activeUntilForDisplay()?->format('d.m.Y') ?? '—',
            'completed_runs' => $completedRuns,
            'total_runs' => $planRuns,
            'remaining_runs' => $remainingRuns,
            'next_planned' => $nextPlanned?->scheduled_date?->format('d.m.Y') ?? '—',
            'status' => $subscription->status_label,
            'auto_renew' => (bool) $subscription->auto_renew,
            'timeline' => $timeline,
            'history' => $history,
        ];
    }

    public function render()
    {
        $view = view('livewire.client.subscriptions-page');

        return $this->embedded ? $view : $view->layout('layouts.client');
    }
}
