<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Subscriptions;

use App\Http\Controllers\Controller;
use App\Models\ClientSubscription;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SubscriptionCheckoutController extends Controller
{
    public function pay(ClientSubscription $subscription): RedirectResponse
    {
        abort_if($subscription->client_id !== auth()->id(), 403);

        abort_unless($subscription->canPay(), 422);

        $order = $this->resolvePendingOrCreate($subscription);

        return redirect()->route('client.payments.show', $order);
    }

    public function renew(ClientSubscription $subscription): RedirectResponse
    {
        abort_if($subscription->client_id !== auth()->id(), 403);

        abort_unless($subscription->canRenew(), 422);

        $order = $this->resolvePendingOrCreate($subscription);

        return redirect()->route('client.payments.show', $order);
    }

    private function resolvePendingOrCreate(ClientSubscription $subscription): Order
    {
        return DB::transaction(function () use ($subscription): Order {
            $lockedSubscription = ClientSubscription::query()
                ->where('id', $subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedSubscription->assertNoActiveScopeConflict();

            $existingPending = $lockedSubscription->generatedOrders()
                ->where('payment_status', Order::PAY_PENDING)
                ->where('origin', Order::ORIGIN_SUBSCRIPTION)
                ->latest('id')
                ->first();

            if ($existingPending) {
                return $existingPending;
            }

            $runAt = $lockedSubscription->next_run_at ?? now();

            return Order::createFromLegacyWebContract([
                'client_id' => (int) $lockedSubscription->client_id,
                'order_type' => Order::TYPE_SUBSCRIPTION,
                'status' => Order::STATUS_NEW,
                'payment_status' => Order::PAY_PENDING,
                'address_id' => $lockedSubscription->address_id,
                'address_text' => (string) ($lockedSubscription->address?->address_text ?? 'Адреса підписки'),
                'lat' => $lockedSubscription->address?->lat,
                'lng' => $lockedSubscription->address?->lng,
                'entrance' => $lockedSubscription->address?->entrance,
                'floor' => $lockedSubscription->address?->floor,
                'apartment' => $lockedSubscription->address?->apartment,
                'intercom' => $lockedSubscription->address?->intercom,
                'comment' => null,
                'scheduled_date' => $runAt->toDateString(),
                'scheduled_time_from' => $runAt->format('H:i'),
                'scheduled_time_to' => $runAt->copy()->addHours(2)->format('H:i'),
                'handover_type' => Order::HANDOVER_DOOR,
                'bags_count' => (int) ($lockedSubscription->plan?->max_bags ?? 1),
                'price' => (int) ($lockedSubscription->plan?->monthly_price ?? 0),
                'client_charge_amount' => (int) ($lockedSubscription->plan?->monthly_price ?? 0),
                'courier_payout_amount' => (int) ($lockedSubscription->plan?->monthly_price ?? 0),
                'system_subsidy_amount' => 0,
                'funding_source' => Order::FUNDING_CLIENT,
                'benefit_type' => null,
                'origin' => Order::ORIGIN_SUBSCRIPTION,
                'subscription_id' => (int) $lockedSubscription->id,
                'promo_code' => null,
                'is_trial' => false,
                'trial_days' => 0,
            ]);
        });
    }
}
