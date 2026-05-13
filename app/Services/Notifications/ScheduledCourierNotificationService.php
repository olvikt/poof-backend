<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;

class ScheduledCourierNotificationService
{
    public function notifyScheduledOrderVisible(Order $order, User $courier): void { $this->emit('scheduled_order_visible', $order, $courier); }
    public function notifyFinalOffer(Order $order, User $courier): void { $this->emit('scheduled_final_offer', $order, $courier); }
    public function notifyOfferExpiringSoon(Order $order, User $courier): void { $this->emit('scheduled_offer_expiring_soon', $order, $courier); }
    public function notifyReservationLost(Order $order, User $courier): void { $this->emit('scheduled_reservation_lost', $order, $courier); }

    public function notifyMarketingNews(User $courier, string $message): void
    {
        if (empty($courier->telegram_chat_id) || ($courier->telegram_notifications_marketing_enabled ?? false) === false) {
            return;
        }

        Http::timeout(5)->post(sprintf('https://api.telegram.org/bot%s/sendMessage', (string) config('services.telegram.bot_token')), [
            'chat_id' => $courier->telegram_chat_id,
            'text' => $message,
        ]);
    }

    private function emit(string $event, Order $order, User $courier): void
    {
        if (($courier->push_notifications_orders_enabled ?? true) === false) {
            return;
        }

        if (empty($courier->telegram_chat_id) || ($courier->telegram_notifications_orders_enabled ?? true) === false) {
            return;
        }

        $dedupKey = sprintf('scheduled_notification:%s:%d:%d', $event, $order->id, $courier->id);
        if (! Cache::add($dedupKey, 1, now()->addMinutes(10))) {
            return;
        }

        Http::timeout(5)->post(sprintf('https://api.telegram.org/bot%s/sendMessage', (string) config('services.telegram.bot_token')), [
            'chat_id' => $courier->telegram_chat_id,
            'text' => $this->renderTelegramMessage($event, $order),
        ]);

        Log::info('scheduled_courier_notification_dispatch', [
            'event' => $event,
            'order_id' => $order->id,
            'courier_id' => $courier->id,
        ]);
    }

    private function renderTelegramMessage(string $event, Order $order): string
    {
        $pickup = (string) ($order->address_text ?: __('courier.notifications.address_fallback'));
        $delivery = (string) ($order->address_text ?: __('courier.notifications.address_fallback'));
        $windowFrom = $order->window_from_at?->format('H:i') ?? $order->scheduled_time_from;
        $windowTo = $order->window_to_at?->format('H:i') ?? $order->scheduled_time_to;
        $window = ($windowFrom && $windowTo) ? sprintf('%s–%s', $windowFrom, $windowTo) : __('courier.notifications.window_fallback');
        $amount = number_format((float) $order->price, 0, ',', ' ') . ' ₴';
        $ttl = (int) config('courier_runtime.scheduled_matching.offer_ttl_seconds', 45);

        return (string) Lang::get('courier.notifications.' . $event, [
            'pickup' => $pickup,
            'delivery' => $delivery,
            'window' => $window,
            'amount' => $amount,
            'ttl' => $ttl,
        ], 'uk');
    }
}
