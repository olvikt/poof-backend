<?php

namespace App\Livewire\Client;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class OrderCreate extends Component
{
    /* =========================================================
     |  ADDRESS
     | ========================================================= */
    public string $address_text = '';
    public ?float $lat = null;
    public ?float $lng = null;

    public ?string $entrance = null;
    public ?string $floor = null;
    public ?string $apartment = null;
    public ?string $intercom = null;
    public ?string $comment = null;

    /* =========================================================
     |  SCHEDULE
     | ========================================================= */
    public ?string $scheduled_date = null;
    public ?string $scheduled_time_from = null;
    public ?string $scheduled_time_to = null;

    /* =========================================================
     |  OPTIONS
     | ========================================================= */
    public string $handover_type = Order::HANDOVER_DOOR;
    public int $bags_count = 1;

    /* =========================================================
     |  PROMO / TRIAL
     | ========================================================= */
    public ?string $promo_code = null;
    public bool $is_trial = false;
    public int $trial_days = 1;

    /* =========================================================
     |  PRICE
     | ========================================================= */
    public int $price = 0;

    /* =========================================================
     |  POPUP STATE
     | ========================================================= */
    public bool $showPaymentModal = false;
    public ?int $createdOrderId = null;

    /* =========================================================
     |  VALIDATION
     | ========================================================= */
    protected function rules(): array
    {
        return [
            'address_text'        => ['required', 'string', 'min:5'],
            'scheduled_date'      => ['required', 'date'],
            'scheduled_time_from' => ['required', 'string'],
            'scheduled_time_to'   => ['nullable', 'string'], 
            'handover_type'       => ['required', 'in:' . Order::HANDOVER_DOOR . ',' . Order::HANDOVER_HAND],
            'bags_count'          => ['required', 'integer', 'min:1', 'max:3'],
            'lat'                 => ['nullable', 'numeric', 'between:-90,90'],
            'lng'                 => ['nullable', 'numeric', 'between:-180,180'],
            'promo_code'          => ['nullable', 'string', 'max:50'],
            'is_trial'            => ['boolean'],
            'trial_days'          => ['nullable', 'integer', 'in:1,3'],
        ];
    }

    public function mount(): void
    {
        $this->recalculatePrice();
    }

    /* =========================================================
     |  MAP → LIVEWIRE
     | ========================================================= */
    #[On('set-location')]
    public function setLocation(
        ?float $lat = null,
        ?float $lng = null,
        ?string $address = null
    ): void {
        if ($lat !== null && $lng !== null) {
            $this->lat = $lat;
            $this->lng = $lng;
        }

        if (! empty($address)) {
            $this->address_text = trim($address);
        }
    }

    /* =========================================================
     |  UI ACTIONS
     | ========================================================= */
    public function selectBags(int $count): void
    {
        $this->bags_count = max(1, min(3, $count));

        if ($this->is_trial) {
            $this->disableTrial();
            return;
        }

        $this->recalculatePrice();
    }

    public function selectTimeSlot(string $from, string $to): void
    {
        $this->scheduled_time_from = $from;
        $this->scheduled_time_to = $to;
    }

    public function selectTrial(int $days): void
    {
        $this->is_trial = true;
        $this->trial_days = in_array($days, [1, 3], true) ? $days : 1;
        $this->price = 0;
    }

    public function disableTrial(): void
    {
        $this->is_trial = false;
        $this->trial_days = 1;
        $this->recalculatePrice();
    }

    /* =========================================================
     |  PRICE
     | ========================================================= */
    protected function recalculatePrice(): void
    {
        $this->price = $this->is_trial
            ? 0
            : Order::calcPriceByBags($this->bags_count);
    }

    /* =========================================================
     |  SUBMIT (CREATE ORDER + POPUP)
     | ========================================================= */
    public function submit(): void
    {
        if ($this->is_trial) {
            $alreadyUsedTrial = Order::query()
                ->where('client_id', Auth::id())
                ->where('is_trial', true)
                ->exists();

            if ($alreadyUsedTrial) {
                session()->flash(
                    'error',
                    'Ви вже скористалися безкоштовним пробним виносом.'
                );
                return;
            }
        }

        $this->validate();
        $this->recalculatePrice();

        $order = Order::create([
            'client_id'           => Auth::id(),
            'order_type'          => Order::TYPE_ONE_TIME,
            'status'              => Order::STATUS_NEW,
            'payment_status'      => $this->is_trial ? Order::PAY_PAID : Order::PAY_PENDING,

            'address_text'        => $this->address_text,
            'lat'                 => $this->lat,
            'lng'                 => $this->lng,
            'entrance'            => $this->entrance,
            'floor'               => $this->floor,
            'apartment'           => $this->apartment,
            'intercom'            => $this->intercom,
            'comment'             => $this->comment,

            'scheduled_date'      => $this->scheduled_date,
            'scheduled_time_from' => $this->scheduled_time_from,
            'scheduled_time_to'   => $this->scheduled_time_to,

            'handover_type'       => $this->handover_type,
            'bags_count'          => $this->bags_count,
            'price'               => $this->price,

            'promo_code'          => $this->promo_code,
            'is_trial'            => $this->is_trial,
            'trial_days'          => $this->is_trial ? $this->trial_days : null,
        ]);

        $this->createdOrderId = $order->id;
        $this->showPaymentModal = true;
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
    }

    public function render()
    {
        return view('livewire.client.order-create', [
            'timeSlots' => Order::allowedTimeSlots(),
            'pricing'   => Order::bagsPricing(),
        ])->layout('layouts.client');
    }
}




