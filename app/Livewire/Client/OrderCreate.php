<?php

namespace App\Livewire\Client;

use App\Livewire\Client\OrderCreate\Concerns\HandlesAddressSelection;
use App\Livewire\Client\OrderCreate\Concerns\HandlesGeocodingMapSync;
use App\Livewire\Client\OrderCreate\Concerns\HandlesOrderSubmission;
use App\Livewire\Client\OrderCreate\Concerns\HandlesPricingTrialPolicy;
use App\Livewire\Client\OrderCreate\Concerns\HandlesScheduleSlots;
use App\Models\Order;
use App\Support\Address\AddressPrecision;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
    public string $address_precision = AddressPrecision::None->value;
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

    public ?string $promo_code = null;
    public bool $is_trial = false;
    public int $trial_days = 1;
    public bool $trial_used = false;
    public int $price = 0;

    public bool $showPaymentModal = false;
    public bool $showTrialBlockedModal = false;
    public ?int $createdOrderId = null;

    protected function rules(): array
    {
        return [
            'address_text' => ['required', 'string', 'min:3'],
            'scheduled_date' => ['required', 'date'],
            'scheduled_time_from' => ['required', 'string'],
            'scheduled_time_to' => ['nullable', 'string'],
            'handover_type' => ['required', 'in:' . Order::HANDOVER_DOOR . ',' . Order::HANDOVER_HAND],
            'bags_count' => ['required', 'integer', 'min:1', 'max:3'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'is_trial' => ['boolean'],
            'trial_days' => ['nullable', 'integer', 'in:1,3'],
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
            'bags_count.min' => 'Мінімум 1 пакет.',
            'bags_count.max' => 'Максимум 3 пакети.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'address_text' => 'адреса',
            'scheduled_date' => 'дата',
            'scheduled_time_from' => 'час',
            'bags_count' => 'кількість пакетів',
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
        $this->trial_used = $this->userAlreadyUsedTrial();
        $this->updateIsCustomDate();
        $this->applyTimeSlot($this->firstAvailableSlotIndex());
        $this->recalculatePrice();

        $this->dispatch('map:init');
    }

    public function render()
    {
        return view('livewire.client.order-create', [
            'timeSlots' => $this->timeSlots,
            'pricing' => Order::bagsPricing(),
            'addresses' => $this->addresses,
        ])->layout('layouts.client');
    }
}
