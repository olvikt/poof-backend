<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CourierOrderInterest;
use App\Models\Order;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class CourierScheduledReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $courierId = (int) optional($request->user())->id;
        $interest = $courierId > 0
            ? CourierOrderInterest::query()->where('order_id', $this->id)->where('courier_id', $courierId)->latest('id')->first()
            : null;

        $windowFromAt = $this->asDateTime($this->window_from_at) ?? $this->legacyWindowAt($this->scheduled_date, $this->scheduled_time_from);
        $windowToAt = $this->asDateTime($this->window_to_at) ?? $this->legacyWindowAt($this->scheduled_date, $this->scheduled_time_to);
        $dispatchAvailableAt = $this->asDateTime($this->dispatch_available_at);
        $finalMatchingStartsAt = $windowFromAt?->subMinutes((int) config('courier_runtime.scheduled_matching.lead_minutes', 30));

        $hasInterest = (bool) ($interest && $interest->status === CourierOrderInterest::STATUS_INTERESTED);

        return [
            'offer_id' => null,
            'order_public_id' => $this->public_id,
            'pickup' => ['address_text' => null],
            'delivery' => null,
            'price' => [
                'courier_payout_amount' => $this->courier_payout_amount !== null ? (int) $this->courier_payout_amount : null,
                'order_price_amount' => $this->price !== null ? (int) $this->price : null,
            ],
            'offer_status' => null,
            'offer_expires_at' => null,
            'seconds_remaining' => null,
            'countdown_active' => false,
            'countdown_started_at' => null,
            'countdown_expires_at' => null,
            'service' => [
                'service_mode' => $this->service_mode,
                'window_from_at' => $windowFromAt?->toISOString(),
                'window_to_at' => $windowToAt?->toISOString(),
            ],
            'is_scheduled' => true,
            'is_future_visible' => true,
            'reservation_stage' => $hasInterest ? 'interested' : 'visible_for_reservation',
            'reservation_stage_label' => $hasInterest ? 'interested' : 'visible for reservation',
            'scheduled_window' => [
                'from' => $windowFromAt?->toISOString(),
                'to' => $windowToAt?->toISOString(),
            ],
            'scheduled_window_label' => $windowFromAt && $windowToAt ? $windowFromAt->format('H:i').'–'.$windowToAt->format('H:i') : null,
            'search_starts_at' => $dispatchAvailableAt?->toISOString(),
            'final_matching_starts_at' => $finalMatchingStartsAt?->toISOString(),
            'dispatch_available_at' => $dispatchAvailableAt?->toISOString(),
            'has_expressed_interest' => $hasInterest,
            'interest_status' => $interest?->status,
            'helper_text' => $hasInterest ? 'Ми підтвердимо виконавця ближче до часу доставки.' : null,
            'primary_cta' => $hasInterest ? 'withdraw_interest' : 'express_interest',
            'primary_cta_label' => $hasInterest ? 'Скасувати готовність' : 'Готовий виконати',
        ];
    }

    private function legacyWindowAt(mixed $date, mixed $time): ?CarbonImmutable
    {
        if (empty($date) || empty($time)) {
            return null;
        }

        return CarbonImmutable::parse(sprintf('%s %s', (string) $date, substr((string) $time, 0, 8)));
    }

    private function asDateTime(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value) && $value !== '') {
            return CarbonImmutable::parse($value);
        }

        return null;
    }
}
