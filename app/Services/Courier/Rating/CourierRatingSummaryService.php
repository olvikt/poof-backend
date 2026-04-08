<?php

declare(strict_types=1);

namespace App\Services\Courier\Rating;

use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CourierRatingSummaryService
{
    public function forCourier(User $courier): array
    {
        $ordersTotal = Order::query()->where('courier_id', $courier->id)->count();
        $ordersCompleted = Order::query()
            ->where('courier_id', $courier->id)
            ->where('status', Order::STATUS_DONE)
            ->count();

        $acceptedOffers = OrderOffer::query()
            ->where('courier_id', $courier->id)
            ->where('status', OrderOffer::STATUS_ACCEPTED)
            ->count();

        $totalClosedOffers = OrderOffer::query()
            ->where('courier_id', $courier->id)
            ->whereIn('status', [OrderOffer::STATUS_ACCEPTED, OrderOffer::STATUS_DECLINED, OrderOffer::STATUS_EXPIRED])
            ->count();

        $expiredOffers = OrderOffer::query()
            ->where('courier_id', $courier->id)
            ->where('status', OrderOffer::STATUS_EXPIRED)
            ->count();

        $customerScore = max(0.0, min(5.0, (float) ($courier->courierProfile?->rating ?? 5.0)));
        $successfulRate = $ordersTotal > 0 ? $ordersCompleted / $ordersTotal : 1.0;
        $acceptedRate = $totalClosedOffers > 0 ? $acceptedOffers / $totalClosedOffers : 1.0;
        $reliability = $totalClosedOffers > 0 ? 1.0 - ($expiredOffers / $totalClosedOffers) : 1.0;
        $tenureMonths = max(0.0, ((float) $courier->created_at?->diffInDays(now())) / 30.0);
        $tenureScore = min(1.0, $tenureMonths / 24.0);

        $score = (
            (($customerScore / 5.0) * 0.35)
            + ($successfulRate * 0.30)
            + ($acceptedRate * 0.20)
            + ($reliability * 0.10)
            + ($tenureScore * 0.05)
        ) * 5.0;

        $summary = [
            'phase' => 'phase1_provisional',
            'current_score' => round($score, 2),
            'max_score' => 5.0,
            'summary' => 'Рейтинг базується на завершеннях, прийнятті оферів, стабільності лінії та оцінках клієнтів.',
            'updated_at' => now()->toIso8601String(),
            'factors' => [
                ['key' => 'customer_score', 'label' => 'Оцінки клієнтів', 'value' => round($customerScore, 2), 'weight' => 0.35],
                ['key' => 'successful_order_rate', 'label' => 'Успішні замовлення', 'value' => round($successfulRate * 100, 1), 'weight' => 0.30],
                ['key' => 'accepted_order_rate', 'label' => 'Прийняття оферів', 'value' => round($acceptedRate * 100, 1), 'weight' => 0.20],
                ['key' => 'line_reliability', 'label' => 'Стабільність на лінії', 'value' => round($reliability * 100, 1), 'weight' => 0.10],
                ['key' => 'tenure', 'label' => 'Стаж у POOF', 'value' => round($tenureMonths, 1), 'weight' => 0.05],
            ],
            'explainability' => [
                'improves' => [
                    'Вчасно завершуйте активні замовлення.',
                    'Приймайте релевантні офери без частих пропусків.',
                    'Підтримуйте стабільну активність без збоїв сесії.',
                ],
                'lowers' => [
                    'Низька частка завершених замовлень.',
                    'Часті пропущені/прострочені офери.',
                    'Падіння оцінок від клієнтів.',
                ],
                'freshness' => 'Оновлення після змін у замовленнях/оферах; контракт Phase 1 (provisional).',
            ],
        ];

        Log::info('courier_rating_summary_render', [
            'courier_id' => $courier->id,
            'phase' => 'phase1',
            'score' => $summary['current_score'],
        ]);

        return $summary;
    }
}
