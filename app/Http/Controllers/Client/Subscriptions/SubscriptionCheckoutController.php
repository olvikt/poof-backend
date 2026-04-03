<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Subscriptions;

use App\Http\Controllers\Controller;
use App\Models\ClientSubscription;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

class SubscriptionCheckoutController extends Controller
{
    public function pay(ClientSubscription $subscription): RedirectResponse
    {
        abort_if($subscription->client_id !== auth()->id(), 403);

        abort_unless($subscription->canPay(), 422);

        $order = $this->resolvePendingOrCreate($subscription, 'initial');

        return redirect()->route('client.payments.show', $order);
    }

    public function renew(ClientSubscription $subscription): RedirectResponse
    {
        abort_if($subscription->client_id !== auth()->id(), 403);

        abort_unless($subscription->canRenew(), 422);

        $order = $this->resolvePendingOrCreate($subscription, 'renewal');

        return redirect()->route('client.payments.show', $order);
    }

    private function resolvePendingOrCreate(ClientSubscription $subscription, string $reason): Order
    {
        $existingPending = $subscription->generatedOrders()
            ->where('payment_status', Order::PAY_PENDING)
            ->where('origin', Order::ORIGIN_SUBSCRIPTION)
            ->latest('id')
            ->first();

        if ($existingPending) {
            return $existingPending;
        }

        $runAt = $subscription->next_run_at ?? now();

        return Order::createFromLegacyWebContract([
            'client_id' => (int) $subscription->client_id,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_id' => $subscription->address_id,
            'address_text' => (string) ($subscription->address?->address_text ?? 'Адреса підписки'),
            'lat' => $subscription->address?->lat,
            'lng' => $subscription->address?->lng,
            'entrance' => $subscription->address?->entrance,
            'floor' => $subscription->address?->floor,
            'apartment' => $subscription->address?->apartment,
            'intercom' => $subscription->address?->intercom,
            'comment' => null,
            'scheduled_date' => $runAt->toDateString(),
            'scheduled_time_from' => $runAt->format('H:i'),
            'scheduled_time_to' => $runAt->copy()->addHours(2)->format('H:i'),
            'handover_type' => Order::HANDOVER_DOOR,
            'bags_count' => (int) ($subscription->plan?->max_bags ?? 1),
            'price' => (int) ($subscription->plan?->monthly_price ?? 0),
            'client_charge_amount' => (int) ($subscription->plan?->monthly_price ?? 0),
            'courier_payout_amount' => (int) ($subscription->plan?->monthly_price ?? 0),
            'system_subsidy_amount' => 0,
            'funding_source' => Order::FUNDING_CLIENT,
            'benefit_type' => null,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'subscription_id' => (int) $subscription->id,
            'promo_code' => null,
            'is_trial' => false,
            'trial_days' => 0,
        ]);
    }
}

