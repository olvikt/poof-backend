<?php

namespace App\Livewire\Client\OrderCreate\Concerns;

use App\Actions\Orders\Create\CreateLegacyWebOrderAction;
use App\Domain\Address\AddressParser;
use App\DTO\Orders\LegacyWebOrderCreatePayload;
use App\Models\ClientAddress;
use App\Models\ClientSubscription;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Services\Orders\Completion\OrderCompletionPolicyAssignmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait HandlesOrderSubmission
{
    public function submit(): void
    {
        if ($this->is_trial && $this->trial_used) {
            $this->showTrialBlockedModal = true;
            return;
        }

        $this->refreshBagPricingOptions();
        $this->validate();
        $this->validateCoordinatesOrFail();

        if ($this->getErrorBag()->has('address_text')) {
            return;
        }

        if (! $this->skipSaveAddressPromptOnce && $this->shouldAskToSaveAddress()) {
            $this->showSaveAddressConfirmModal = true;
            return;
        }

        if (! $this->skipSaveAddressPromptOnce && $this->duplicateAddressId) {
            $this->dispatch('notify', type: 'info', message: 'Такий адрес уже є в Моїх адресах. Оберіть його зі збережених.');
        }

        $this->skipSaveAddressPromptOnce = false;
        $this->showSaveAddressConfirmModal = false;
        $this->submitOrderAndOpenPayment();
    }

    public function confirmSaveAddressAndContinue(): void
    {
        $this->showSaveAddressConfirmModal = false;
        $this->skipSaveAddressPromptOnce = true;

        $duplicate = $this->findDuplicateAddress();

        if ($duplicate) {
            $this->duplicateAddressId = (int) $duplicate->id;
            $this->dispatch('notify', type: 'info', message: 'Такий адрес уже є в Моїх адресах. Оберіть його зі збережених.');
        } else {
            $this->saveCurrentAddressToAddressBook();
        }

        $this->submit();
    }

    public function declineSaveAddressAndContinue(): void
    {
        $this->showSaveAddressConfirmModal = false;
        $this->skipSaveAddressPromptOnce = true;
        $this->submit();
    }

    protected function submitOrderAndOpenPayment(): void
    {
        $this->recalculatePrice();

        $isSubscriptionCheckout = $this->selected_subscription_plan_id !== null;
        $subscriptionId = $this->createClientSubscriptionIfSelected();
        $courierPayoutAmount = (int) Order::calcPriceByBags($this->bags_count);
        $clientChargeAmount = (int) $this->price;
        $systemSubsidyAmount = max(0, $courierPayoutAmount - $clientChargeAmount);
        $isWelcomeBenefit = $this->is_trial && $this->trial_days === 1;

        $order = app(CreateLegacyWebOrderAction::class)->handle(
            clientId: (int) Auth::id(),
            payload: LegacyWebOrderCreatePayload::fromArray([
                'address_id' => $this->address_id,
                'address_text' => $this->address_text,
                'lat' => $this->lat,
                'lng' => $this->lng,
                'entrance' => $this->entrance,
                'floor' => $this->floor,
                'apartment' => $this->apartment,
                'intercom' => $this->intercom,
                'comment' => $this->comment,
                'scheduled_date' => $this->scheduled_date,
                'scheduled_time_from' => $this->service_mode === Order::SERVICE_MODE_PREFERRED_WINDOW ? $this->scheduled_time_from : null,
                'scheduled_time_to' => $this->service_mode === Order::SERVICE_MODE_PREFERRED_WINDOW ? $this->scheduled_time_to : null,
                'service_mode' => $this->service_mode,
                'client_wait_preference' => $this->client_wait_preference,
                'promise_policy_version' => (string) config('order_promise.policy_version', 'v1'),
                'handover_type' => $this->handover_type,
                'completion_policy' => app(OrderCompletionPolicyAssignmentService::class)->assignForCreate($this->handover_type),
                'bags_count' => $this->bags_count,
                'price' => $this->price,
                'client_charge_amount' => $clientChargeAmount,
                'courier_payout_amount' => $courierPayoutAmount,
                'system_subsidy_amount' => $systemSubsidyAmount,
                'funding_source' => $isWelcomeBenefit ? Order::FUNDING_SYSTEM_PROMO : Order::FUNDING_CLIENT,
                'benefit_type' => $isWelcomeBenefit ? Order::BENEFIT_WELCOME_FIRST_ORDER_FREE : null,
                'origin' => Order::ORIGIN_CHECKOUT,
                'subscription_id' => $subscriptionId,
                'order_type' => $isSubscriptionCheckout ? Order::TYPE_SUBSCRIPTION : Order::TYPE_ONE_TIME,
                'payment_status' => $isWelcomeBenefit ? Order::PAY_PAID : Order::PAY_PENDING,
                'promo_code' => $this->promo_code,
                'is_trial' => $this->is_trial,
                'trial_days' => $this->trial_days,
            ]),
        );

        $this->createdOrderId = $order->id;
        $this->showPaymentModal = true;

        if ($this->is_trial) {
            $this->trial_used = true;
        }
    }

    protected function createClientSubscriptionIfSelected(): ?int
    {
        if (! $this->selected_subscription_plan_id) {
            return null;
        }

        $plan = SubscriptionPlan::query()->active()->find($this->selected_subscription_plan_id);

        if (! $plan) {
            return null;
        }

        $subscription = DB::transaction(function () use ($plan): ClientSubscription {
            $clientId = (int) Auth::id();

            $existing = ClientSubscription::query()
                ->where('client_id', $clientId)
                ->where('address_id', $this->address_id)
                ->where('status', ClientSubscription::STATUS_ACTIVE)
                ->lockForUpdate()
                ->orderBy('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            return ClientSubscription::unguarded(function () use ($plan, $clientId): ClientSubscription {
                return ClientSubscription::query()->create([
                    'client_id' => $clientId,
                    'subscription_plan_id' => (int) $plan->id,
                    'address_id' => $this->address_id,
                    'status' => ClientSubscription::STATUS_ACTIVE,
                    'next_run_at' => Carbon::parse(sprintf('%s %s', (string) $this->scheduled_date, (string) $this->scheduled_time_from)),
                    'ends_at' => Carbon::now()->addMonth(),
                    'auto_renew' => true,
                    'renewals_count' => 0,
                    'meta' => [
                        'frequency_type' => $plan->frequency_type,
                        'checkout_origin' => 'checkout',
                    ],
                ]);
            });
        });

        return (int) $subscription->id;
    }

    protected function shouldAskToSaveAddress(): bool
    {
        if ($this->address_id) {
            return false;
        }

        $this->duplicateAddressId = null;
        $duplicate = $this->findDuplicateAddress();

        if ($duplicate) {
            $this->duplicateAddressId = (int) $duplicate->id;
            return false;
        }

        return filled($this->street) && filled($this->house) && filled($this->address_text);
    }

    protected function saveCurrentAddressToAddressBook(): void
    {
        $userId = (int) Auth::id();

        if ($userId <= 0) {
            return;
        }

        ClientAddress::createForUser($userId, [
            'label' => 'home',
            'title' => 'Дім',
            'building_type' => 'apartment',
            'address_text' => $this->address_text,
            'city' => $this->city,
            'street' => $this->street,
            'house' => $this->house,
            'entrance' => $this->entrance,
            'intercom' => $this->intercom,
            'floor' => $this->floor,
            'apartment' => $this->apartment,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'geocode_source' => 'manual',
            'geocode_accuracy' => 'exact',
            'geocoded_at' => now(),
        ]);

        $this->reloadAddresses();
        $this->dispatch('notify', type: 'success', message: 'Адресу збережено в Мої адреси.');
    }

    protected function findDuplicateAddress(): ?ClientAddress
    {
        $userId = (int) Auth::id();

        if ($userId <= 0) {
            return null;
        }

        $street = $this->normalizedStreetForDedupe();
        $house = $this->normalizedHouseForDedupe();

        if (! filled($street) || ! filled($house)) {
            return null;
        }

        $lat = $this->normalizedLatForDedupe();
        $lng = $this->normalizedLngForDedupe();

        return ClientAddress::query()
            ->where('user_id', $userId)
            ->latest('id')
            ->get()
            ->first(function (ClientAddress $address) use ($street, $house, $lat, $lng): bool {
                $sameStreet = $this->normalizeFreeText($address->street) === $street;
                $sameHouse = $this->normalizeFreeText((string) app(AddressParser::class)->normalizeHouse($address->house)) === $house;

                if (! $sameStreet || ! $sameHouse) {
                    return false;
                }

                if ($lat === null || $lng === null || $address->lat === null || $address->lng === null) {
                    return true;
                }

                return round((float) $address->lat, 4) === $lat
                    && round((float) $address->lng, 4) === $lng;
            });
    }

    protected function normalizedStreetForDedupe(): ?string
    {
        $parser = app(AddressParser::class);
        $street = $parser->normalizeStreet($this->street);

        if (! $street && filled($this->address_text)) {
            if (preg_match('/^(.*?)[,\s]+(\d+[A-Za-zА-Яа-яІЇЄієї\-\/]*)/u', (string) $this->address_text, $matches)) {
                $street = $parser->normalizeStreet($matches[1] ?? null);
            }
        }

        return $this->normalizeFreeText($street);
    }

    protected function normalizedHouseForDedupe(): ?string
    {
        $parser = app(AddressParser::class);
        $house = $parser->normalizeHouse($this->house);

        if (! $house && filled($this->address_text)) {
            if (preg_match('/^(.*?)[,\s]+(\d+[A-Za-zА-Яа-яІЇЄієї\-\/]*)/u', (string) $this->address_text, $matches)) {
                $house = $parser->normalizeHouse($matches[2] ?? null);
            }
        }

        return $this->normalizeFreeText($house);
    }

    protected function normalizedLatForDedupe(): ?float
    {
        return $this->lat === null ? null : round((float) $this->lat, 4);
    }

    protected function normalizedLngForDedupe(): ?float
    {
        return $this->lng === null ? null : round((float) $this->lng, 4);
    }

    protected function normalizeFreeText(?string $value): ?string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value));

        if (! $value) {
            return null;
        }

        return mb_strtolower($value);
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
    }
}
