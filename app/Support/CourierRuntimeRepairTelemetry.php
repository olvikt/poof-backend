<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;

class CourierRuntimeRepairTelemetry
{
    /**
     * @param  array<string,mixed>  $before
     * @param  array<string,mixed>  $after
     */
    public static function emitIfChanged(
        int $userId,
        ?int $courierId,
        array $before,
        array $after,
        bool $hadActiveOrder,
        ?string $courierStatus,
        string $sourceContext,
    ): void {
        $changes = [];

        foreach ($after as $field => $to) {
            $from = $before[$field] ?? null;
            if ($from === $to) {
                continue;
            }

            $changes[$field] = [
                'from' => self::normalize($from),
                'to' => self::normalize($to),
            ];
        }

        if ($changes === []) {
            return;
        }

        Log::info('courier_runtime_repair_write', [
            'user_id' => $userId,
            'courier_id' => $courierId,
            'field_changes' => $changes,
            'had_active_order' => $hadActiveOrder,
            'courier_status' => $courierStatus,
            'source_context' => $sourceContext,
            'counter' => 'courier_runtime_repair_writes_total',
            'counter_increment' => 1,
        ]);
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return $value;
    }
}
