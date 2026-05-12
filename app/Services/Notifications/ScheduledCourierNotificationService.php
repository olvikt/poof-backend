<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ScheduledCourierNotificationService
{
    public function notifyScheduledOrderVisible(Order $order, User $courier): void { $this->emit('scheduled_order_visible', $order, $courier); }
    public function notifyFinalOffer(Order $order, User $courier): void { $this->emit('scheduled_final_offer', $order, $courier); }
    public function notifyOfferExpiringSoon(Order $order, User $courier): void { $this->emit('scheduled_offer_expiring_soon', $order, $courier); }
    public function notifyReservationLost(Order $order, User $courier): void { $this->emit('scheduled_reservation_lost', $order, $courier); }

    private function emit(string $event, Order $order, User $courier): void
    {
        if (($courier->telegram_notifications_orders_enabled ?? true) === false || ($courier->push_notifications_orders_enabled ?? true) === false) {
            return;
        }

        Log::info('scheduled_courier_notification_dispatch', [
            'event' => $event,
            'order_id' => $order->id,
            'courier_id' => $courier->id,
        ]);
    }
}
