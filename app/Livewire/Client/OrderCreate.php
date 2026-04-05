<?php

namespace App\Livewire\Client;

use App\Livewire\Client\OrderCreate\Concerns\HandlesAddressSelection;
use App\Livewire\Client\OrderCreate\Concerns\HandlesGeocodingMapSync;
use App\Livewire\Client\OrderCreate\Concerns\HandlesOrderSubmission;
use App\Livewire\Client\OrderCreate\Concerns\HandlesPricingTrialPolicy;
use App\Livewire\Client\OrderCreate\Concerns\HandlesScheduleSlots;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Domain\Address\Precision;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

class OrderCreate extends Component
{
    use HandlesAddressSelection;
    use HandlesGeocodingMapSync;
    use HandlesScheduleSlots;
    use HandlesPricingTrialPolicy;
    use HandlesOrderSubmission;

    public Collection $addresses;

    public ?int $address_id = null;
    public string $address_text = '';
    public ?string $street = null;
    public ?string $house = null;
    public ?string $city = null;
    public ?float $lat = null;
    public ?float $lng = null;
    public bool $coordsFromAddressBook = false;
    public string $address_precision = Precision::None->value;
    protected ?string $geocodeToken = null;

    public ?string $entrance = null;
    public ?string $floor = null;
    public ?string $apartment = null;
    public ?string $intercom = null;
    public ?string $comment = null;
    public bool $suppressAddressHooks = false;

    public ?string $scheduled_date = null;
    public ?string $scheduled_time_from = null;
    public ?string $scheduled_time_to = null;
    public string $service_mode = Order::SERVICE_MODE_PREFERRED_WINDOW;
    public string $client_wait_preference = Order::WAIT_AUTO_CANCEL_IF_NOT_FOUND;
    public bool $promise_consent = true;
    public int $timeSlot = 0;

    public array $timeSlots = [
        ['from' => '08:00', 'to' => '10:00', 'enabled' => true],
        ['from' => '10:00', 'to' => '12:00', 'enabled' => true],
        ['from' => '12:00', 'to' => '14:00', 'enabled' => true],
        ['from' => '14:00', 'to' => '16:00', 'enabled' => true],
        ['from' => '16:00', 'to' => '18:00', 'enabled' => true],
        ['from' => '18:00', 'to' => '20:00', 'enabled' => true],
        ['from' => '20:00', 'to' => '22:00', 'enabled' => false],
    ];

    public bool $isCustomDate = false;
    public string $handover_type = Order::HANDOVER_DOOR;
    public int $bags_count = 1;
    public array $bagPricingOptions = [];

    public ?string $promo_code = null;
    public bool $is_trial = false;
    public int $trial_days = 1;
    public bool $trial_used = false;
    public bool $showSubscriptionModal = false;
    public ?string $subscription_frequency = null;
    public ?int $selected_subscription_plan_id = null;
    public int $price = 0;

    public bool $showPaymentModal = false;
    public bool $showTrialBlockedModal = false;
    public bool $showSaveAddressConfirmModal = false;
    public ?int $createdOrderId = null;
    public bool $skipSaveAddressPromptOnce = false;
    public ?int $duplicateAddressId = null;

    protected function rules(): array
    {
        return [
            'address_text' => ['required', 'string', 'min:3'],
            'scheduled_date' => ['required', 'date'],
            'scheduled_time_from' => [$this->service_mode === Order::SERVICE_MODE_PREFERRED_WINDOW ? 'required' : 'nullable', 'string'],
            'scheduled_time_to' => ['nullable', 'string'],
            'service_mode' => ['required', 'in:' . Order::SERVICE_MODE_ASAP . ',' . Order::SERVICE_MODE_PREFERRED_WINDOW],
            'client_wait_preference' => ['required', 'in:' . Order::WAIT_AUTO_CANCEL_IF_NOT_FOUND . ',' . Order::WAIT_ALLOW_LATE_FULFILLMENT],
            'promise_consent' => ['accepted'],
            'handover_type' => ['required', 'in:' . Order::HANDOVER_DOOR . ',' . Order::HANDOVER_HAND],
            'bags_count' => ['required', 'integer', Rule::in(array_keys($this->bagPricingOptions))],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'is_trial' => ['boolean'],
            'trial_days' => ['nullable', 'integer', 'in:1'],
        ];
    }

    protected function messages(): array
    {
        return [
            'address_text.required' => 'Вкажіть адресу.',
            'address_text.min' => 'Адреса занадто коротка.',
            'scheduled_date.required' => 'Оберіть дату.',
            'scheduled_date.date' => 'Некоректна дата.',
            'scheduled_time_from.required' => 'Оберіть час.',
            'bags_count.in' => 'Оберіть доступний тариф за кількістю мішків.',
            'promise_consent.accepted' => 'Підтвердьте умови виконання замовлення.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'address_text' => 'адреса',
            'scheduled_date' => 'дата',
            'scheduled_time_from' => 'час',
            'bags_count' => 'кількість пакетів',
            'promise_consent' => 'умови виконання',
        ];
    }

    public function mount(): void
    {
        $repeatId = request()->integer('repeat');

        if ($repeatId) {
            $order = Order::query()
                ->where('id', $repeatId)
                ->where('client_id', auth()->id())
                ->first();

            if ($order) {
                $this->hydrateFromOrder($order);
            }
        }

        if (! $repeatId) {
            $this->address_id = request()->integer('address_id');

            if ($this->address_id) {
                $this->loadAddressFromBook($this->address_id);
            }
        }

        if (! $this->scheduled_date) {
            $this->scheduled_date = Carbon::today()->toDateString();
        }

        $this->reloadAddresses();
        $this->client_wait_preference = (string) config('order_promise.default_wait_preference', Order::WAIT_AUTO_CANCEL_IF_NOT_FOUND);
        $this->trial_used = $this->userAlreadyUsedTrial();
        $this->updateIsCustomDate();
        $this->applyTimeSlot($this->firstAvailableSlotIndex());
        $this->refreshBagPricingOptions();
        $this->recalculatePrice();

        $this->dispatch('map:init');
    }

    public function render()
    {
        return view('livewire.client.order-create', [
            'timeSlots' => $this->timeSlots,
            'pricing' => $this->bagPricingOptions,
            'addresses' => $this->addresses,
            'subscriptionOptions' => $this->subscriptionOptions(),
        ])->layout('layouts.client');
    }

    protected function subscriptionOptions(): array
    {
        $plans = SubscriptionPlan::query()
            ->active()
            ->ordered()
            ->get();

        return $plans->map(function (SubscriptionPlan $plan): array {
            return [
                'id' => (int) $plan->id,
                'key' => $plan->slug,
                'title' => $plan->name,
                'description' => $plan->description,
                'monthly_price' => (int) $plan->monthly_price,
                'pickups_per_month' => (int) $plan->pickups_per_month,
                'approx_price_per_pickup' => $plan->approxPricePerPickup(),
                'saving_percent' => $plan->economyPercent(),
                'max_bags' => (int) $plan->max_bags,
                'max_weight_kg' => (int) $plan->max_weight_kg,
                'is_selected' => (int) $this->selected_subscription_plan_id === (int) $plan->id,
            ];
        })->all();
    }

    protected function refreshBagPricingOptions(): void
    {
        $this->bagPricingOptions = Order::bagsPricing();

        if ($this->bagPricingOptions === []) {
            return;
        }

        if (! array_key_exists($this->bags_count, $this->bagPricingOptions)) {
            $this->bags_count = (int) array_key_first($this->bagPricingOptions);
        }
    }
}
