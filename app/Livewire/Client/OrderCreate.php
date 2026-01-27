<?php

namespace App\Livewire\Client;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
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

    /**
     * Slot slider index (0..6)
     */
    public int $timeSlot = 0;

    /**
     * 7 slots, last is reserved (disabled)
     */
    public array $timeSlots = [
        ['from' => '08:00', 'to' => '10:00', 'enabled' => true],
        ['from' => '10:00', 'to' => '12:00', 'enabled' => true],
        ['from' => '12:00', 'to' => '14:00', 'enabled' => true],
        ['from' => '14:00', 'to' => '16:00', 'enabled' => true],
        ['from' => '16:00', 'to' => '18:00', 'enabled' => true],
        ['from' => '18:00', 'to' => '20:00', 'enabled' => true],
        ['from' => '20:00', 'to' => '22:00', 'enabled' => false], // резерв (поки не можна)
    ];

    /**
     * ✅ ВАЖНО: ты используешь updateIsCustomDate(), где пишешь в $this->isCustomDate
     * Чтобы не создавать динамическое свойство (PHP 8.2), объявляем явно.
     */
    public bool $isCustomDate = false;

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

    /**
     * ✅ ВАЖНО: нужен для UI (disabled на trial options)
     */
    public bool $trial_used = false;

    /* =========================================================
     |  PRICE
     | ========================================================= */
    public int $price = 0;

    /* =========================================================
     |  POPUP STATE
     | ========================================================= */
    public bool $showPaymentModal = false;
    public bool $showTrialBlockedModal = false;
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

    /* =========================================================
     |  LIFECYCLE
     | ========================================================= */
    public function mount(): void
    {
        // дефолтна дата = сьогодні
        if (! $this->scheduled_date) {
            $this->scheduled_date = Carbon::today()->toDateString();
        }

        $this->updateIsCustomDate();

        // ✅ один раз определяем: пробный уже был?
        $this->trial_used = $this->userAlreadyUsedTrial();

        // выставляем корректный слот времени под дату
        $idx = $this->firstAvailableSlotIndex();
        $this->applyTimeSlot($idx);

        $this->recalculatePrice();

        /**
         * ✅ Инициализация карты на клиенте
         * Событие придет браузеру вместе с первым ответом Livewire.
         */
        $this->dispatch('map:init');
    }

    /**
     * ✅ Livewire v3: вызывается после каждого рендера компонента.
     * Это самый надежный момент, когда DOM (включая #map) уже существует.
     * Даже если событие прилетит несколько раз — наш JS initMap должен быть идемпотентным.
     */
    public function rendered(): void
    {
        $this->dispatch('map:init');
    }

    /* =========================================================
     |  TIME SLOTS (HELPERS)
     | ========================================================= */
    protected function firstAvailableSlotIndex(): int
    {
        $now = now();

        // scheduled_date у нас строка 'YYYY-MM-DD'
        $selectedDate = $this->scheduled_date
            ? Carbon::parse($this->scheduled_date)
            : Carbon::today();

        $isToday = $selectedDate->isSameDay($now);

        foreach ($this->timeSlots as $idx => $slot) {
            if (!($slot['enabled'] ?? true)) {
                continue;
            }

            // ВАЖНО: сравниваем время только если выбрана дата "сегодня"
            if ($isToday) {
                $from = Carbon::createFromFormat('H:i', $slot['from'])->setDate(
                    $now->year,
                    $now->month,
                    $now->day
                );

                // берем первый слот, который строго позже текущего времени
                if ($from->greaterThan($now)) {
                    return $idx;
                }
            } else {
                // если не сегодня — первый доступный enabled слот
                return $idx;
            }
        }

        // fallback — 0 (если всё disabled/всё прошло)
        return 0;
    }

    protected function updateIsCustomDate(): void
    {
        if (! $this->scheduled_date) {
            $this->isCustomDate = false;
            return;
        }

        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $this->isCustomDate = ! in_array(
            $this->scheduled_date,
            [$today, $tomorrow],
            true
        );
    }

    /**
     * Apply time slot by index (0..6). If disabled -> rollback to nearest enabled on the left.
     */
    public function applyTimeSlot(int $idx): void
    {
        $count = count($this->timeSlots);
        if ($count === 0) {
            $this->timeSlot = 0;
            $this->scheduled_time_from = null;
            $this->scheduled_time_to = null;
            return;
        }

        $idx = max(0, min($idx, $count - 1));

        // если слот disabled — откатываемся влево к доступному
        if (!($this->timeSlots[$idx]['enabled'] ?? true)) {
            for ($j = $idx - 1; $j >= 0; $j--) {
                if (($this->timeSlots[$j]['enabled'] ?? true) === true) {
                    $idx = $j;
                    break;
                }
            }
        }

        $this->timeSlot = $idx;
        $this->scheduled_time_from = $this->timeSlots[$idx]['from'] ?? null;
        $this->scheduled_time_to   = $this->timeSlots[$idx]['to'] ?? null;
    }

    public function updatedScheduledDate(): void
    {
        $this->updateIsCustomDate();

        $idx = $this->firstAvailableSlotIndex();
        $this->applyTimeSlot($idx);

        // если дата изменилась — просто пересчитаем цену (на всякий)
        $this->recalculatePrice();

        // ✅ после смены даты — часто обновляется DOM, пусть карта подтянется/пересчитает размер
        $this->dispatch('map:init');
    }

    /**
     * Legacy кнопки часу (если ещё используются где-то).
     */
    public function selectTimeSlot(string $from, string $to): void
    {
        $this->scheduled_time_from = $from;
        $this->scheduled_time_to = $to;

        foreach ($this->timeSlots as $idx => $slot) {
            if (($slot['from'] ?? null) === $from && ($slot['to'] ?? null) === $to) {
                if (($slot['enabled'] ?? true) === true) {
                    $this->timeSlot = (int) $idx;
                }
                break;
            }
        }
    }

    /* =========================================================
     |  COMPUTED / DERIVED STATE
     | ========================================================= */
    public function getIsCustomDateProperty(): bool
    {
        if (! $this->scheduled_date) {
            return false;
        }

        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        return ! in_array($this->scheduled_date, [$today, $tomorrow], true);
    }
	
	#[On('set-scheduled-date')]
	public function setScheduledDate(string $date): void
	{
		$this->scheduled_date = $date;
	}

	#[On('set-time-slot')]
	public function setTimeSlot(int $index): void
	{
		$this->applyTimeSlot($index);
	}
	

    /* =========================================================
     |  MAP → LIVEWIRE
     | ========================================================= */
    #[On('set-location')]
    public function setLocation(float $lat, float $lng): void
    {
        $this->lat = $lat;
        $this->lng = $lng;

        $this->address_text = $this->reverseGeocode($lat, $lng);

        // ✅ после выбора точки — можно дернуть init, чтобы маркер/центр гарантированно отобразились
        $this->dispatch('map:init');
    }

    protected function reverseGeocode(float $lat, float $lng): string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'POOF App (contact@poof.com.ua)',
            ])->get('https://nominatim.openstreetmap.org/reverse', [
                'format' => 'jsonv2',
                'lat'    => $lat,
                'lon'    => $lng,
            ]);

            if (! $response->successful()) {
                return '';
            }

            $data = $response->json();

            $road  = $data['address']['road'] ?? '';
            $house = $data['address']['house_number'] ?? '';

            return trim("$road $house");
        } catch (\Throwable $e) {
            return '';
        }
    }

    /* =========================================================
     |  UI ACTIONS
     | ========================================================= */
    public function selectBags(int $count): void
    {
        $this->bags_count = max(1, min(3, $count));

        // trial несовместим с выбором мешков (как у тебя было)
        if ($this->is_trial) {
            $this->disableTrial();
            return;
        }

        $this->recalculatePrice();
    }

    public function selectTrial(int $days): void
    {
        // ✅ единая точка правды
        if ($this->trial_used) {
            $this->showTrialBlockedModal = true;
            return;
        }

        $this->is_trial = true;
        $this->trial_days = in_array($days, [1, 3], true) ? $days : 1;

        // trial = 1 мешок и 0 цена (как у тебя)
        $this->bags_count = 1;
        $this->price = 0;
    }

    protected function userAlreadyUsedTrial(): bool
    {
        // ✅ ВАЖНО: у тебя в create используется client_id
        return Order::query()
            ->where('client_id', Auth::id())
            ->where('is_trial', true)
            ->exists();
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
        // ✅ не молчим — показываем модалку, если пробный уже был
        if ($this->is_trial && $this->trial_used) {
            $this->showTrialBlockedModal = true;
            return;
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

        // ✅ после успешного trial — обновим флаг, чтобы UI сразу блокировал выбор
        if ($this->is_trial) {
            $this->trial_used = true;
        }
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
    }

    /* =========================================================
     |  VIEW
     | ========================================================= */
    public function render()
    {
        return view('livewire.client.order-create', [
            'timeSlots' => $this->timeSlots,
            'pricing'   => Order::bagsPricing(),
        ])->layout('layouts.client');
    }
}





