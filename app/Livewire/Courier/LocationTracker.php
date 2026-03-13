<?php

namespace App\Livewire\Courier;

use Livewire\Component;
use App\Models\Courier;
use App\Models\User;
use App\Services\Dispatch\OfferDispatcher;

class LocationTracker extends Component
{
    /**
     * JS → Livewire listener
     */
    protected $listeners = [
        'courier-location' => 'updateLocation',
    ];

    /**
     * 📍 Получение координат от фронта
     * ⚠ Без type-hint'ов (требование Livewire listeners)
     */
	 
	    public function mount(): void
		{
			$user = auth()->user();

			if (
				$user instanceof User &&
				$user->isCourier() &&
				$user->is_online &&
				$user->last_lat &&
				$user->last_lng
			) {
				$this->dispatch('map:courier-update', [
					'courier' => [
						'lat' => $user->last_lat,
						'lng' => $user->last_lng,
					],
				]);
			}
		} 
	 
    public function updateLocation($lat, $lng, $accuracy = null): void
    {
        $user = auth()->user();

        // 🔒 Только авторизованный курьер
        if (! $user instanceof User || ! $user->isCourier()) {
            return;
        }

        // Приведение типов
        $lat = (float) $lat;
        $lng = (float) $lng;

        // ❌ защита от мусора
        if (
            $lat < -90 || $lat > 90 ||
            $lng < -180 || $lng > 180
        ) {
            return;
        }

        // -------------------------------------------------
        // Обновляем координаты
        // -------------------------------------------------

        $user->update([
            'last_lat'     => $lat,
            'last_lng'     => $lng,
            'last_seen_at' => now(),
        ]);

        $courierProfile = $user->courierProfile;

        if ($courierProfile) {
            $courierData = [
                'last_location_at' => now(),
            ];

            if ($user->is_online && $courierProfile->status === Courier::STATUS_OFFLINE) {
                $courierData['status'] = Courier::STATUS_ONLINE;
            }

            $courierProfile->update($courierData);
        }

        // -------------------------------------------------
        // Если курьер ONLINE — запускаем dispatcher
        // -------------------------------------------------

        if ($user->is_online) {

            // 🔒 анти-спам: не чаще 1 раза в 5 секунд
            if (
                ! $user->last_dispatch_at ||
                $user->last_dispatch_at->diffInSeconds(now()) >= 5
            ) {
                app(OfferDispatcher::class)->dispatchSearchingOrders(20);

                $user->update([
                    'last_dispatch_at' => now(),
                ]);
            }
        }

        // -------------------------------------------------
        // Обновляем карту (JS)
        // -------------------------------------------------

        $this->dispatch('map:courier-update', [
            'courier' => [
                'lat' => $lat,
                'lng' => $lng,
            ],
        ]);
    }

    /**
     * Headless component
     */
    public function render()
    {
        return view('livewire.courier.location-tracker');
    }
}
