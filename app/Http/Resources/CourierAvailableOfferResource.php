<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CourierOrderInterest;
use App\Models\Order;
use App\Models\OrderOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderOffer */
class CourierAvailableOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order = $this->order;
        $now = now();
        $secondsRemaining = $this->expires_at ? (int) max(0, $now->diffInSeconds($this->expires_at, false)) : null;
        $countdownActive = $secondsRemaining !== null && $secondsRemaining > 0 && $this->status === OrderOffer::STATUS_PENDING;
        $courierId = (int) optional($request->user())->id;

        $interest = $courierId > 0 && $order
            ? CourierOrderInterest::query()->where('order_id', $order->id)->where('courier_id', $courierId)->latest('id')->first()
            : null;

        $isScheduled = $this->isScheduledOrder($order);
        $isAssigned = $order && (int) $order->courier_id === $courierId;

        $reservationStage = $this->resolveReservationStage($order, $interest, $isAssigned, $now);
        $primaryCta = $this->resolvePrimaryCta($reservationStage);

        return [
            'offer_id' => (int) $this->id,
            'order_public_id' => $order?->public_id,
            'pickup' => [
                'address_text' => $isScheduled && ! $isAssigned ? null : $order?->address_text,
            ],
            'delivery' => null,
            'price' => [
                'courier_payout_amount' => $order?->courier_payout_amount !== null ? (int) $order->courier_payout_amount : null,
                'order_price_amount' => $order?->price !== null ? (int) $order->price : null,
            ],
            'offer_status' => (string) $this->status,
            'offer_expires_at' => optional($this->expires_at)?->toISOString(),
            'seconds_remaining' => $secondsRemaining,
            'countdown_active' => $countdownActive,
            'countdown_started_at' => $countdownActive ? optional($this->created_at)?->toISOString() : null,
            'countdown_expires_at' => $countdownActive ? optional($this->expires_at)?->toISOString() : null,
            'service' => [
                'service_mode' => $order?->service_mode,
                'window_from_at' => optional($order?->window_from_at)?->toISOString(),
                'window_to_at' => optional($order?->window_to_at)?->toISOString(),
            ],
            'is_scheduled' => $isScheduled,
            'is_future_visible' => $isScheduled,
            'reservation_stage' => $reservationStage,
            'reservation_stage_label' => $this->stageLabel($reservationStage),
            'scheduled_window' => [
                'from' => optional($order?->window_from_at)?->toISOString(),
                'to' => optional($order?->window_to_at)?->toISOString(),
            ],
            'scheduled_window_label' => $this->windowLabel($order),
            'search_starts_at' => optional($order?->dispatch_available_at)?->toISOString(),
            'final_matching_starts_at' => optional($order?->window_from_at)?->subMinutes((int) config('courier_runtime.scheduled_matching.lead_minutes', 30))->toISOString(),
            'dispatch_available_at' => optional($order?->dispatch_available_at)?->toISOString(),
            'has_expressed_interest' => (bool) ($interest && $interest->status === CourierOrderInterest::STATUS_INTERESTED),
            'interest_status' => $interest?->status,
            'helper_text' => $this->helperText($reservationStage),
            'primary_cta' => $primaryCta,
            'primary_cta_label' => $this->ctaLabel($primaryCta, $secondsRemaining),
        ];
    }

    private function isScheduledOrder(?Order $order): bool { return $order?->service_mode === Order::SERVICE_MODE_PREFERRED_WINDOW || $order?->scheduled_date !== null; }
    private function resolveReservationStage(?Order $order, ?CourierOrderInterest $interest, bool $isAssigned, $now): string {
        if ($isAssigned) return 'assigned';
        if ($interest && $interest->status === CourierOrderInterest::STATUS_REJECTED && $interest->rejected_reason === 'selected_elsewhere') return 'selected_elsewhere';
        if ($this->status === OrderOffer::STATUS_EXPIRED || ($this->expires_at && $this->expires_at->lte($now))) return 'expired';
        if ($this->status === OrderOffer::STATUS_PENDING) return 'offered';
        if ($interest && $interest->status === CourierOrderInterest::STATUS_INTERESTED) return 'interested';
        return 'visible_for_reservation';
    }
    private function resolvePrimaryCta(string $stage): string { return match($stage){'interested'=>'withdraw_interest','offered'=>'accept_offer','assigned'=>'view_assigned_order',default=>'express_interest'}; }
    private function stageLabel(string $stage): string { return str_replace('_', ' ', $stage); }
    private function helperText(string $stage): ?string { return match($stage){'interested'=>'Ми підтвердимо виконавця ближче до часу доставки.','selected_elsewhere'=>'Інший курʼєр уже підтвердив це замовлення.',default=>null}; }
    private function ctaLabel(string $cta, ?int $seconds): string { return match($cta){'withdraw_interest'=>'Скасувати готовність','accept_offer'=>'Прийняти за '.(int)($seconds ?? 0).' сек','view_assigned_order'=>'Перейти до замовлення',default=>'Готовий виконати'}; }
    private function windowLabel(?Order $order): ?string { if (! $order?->window_from_at || ! $order?->window_to_at) return null; return $order->window_from_at->format('H:i').'–'.$order->window_to_at->format('H:i'); }
}
