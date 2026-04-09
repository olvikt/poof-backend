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
        string $repairType = 'unknown',
        string $repairReason = 'unspecified',
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

        foreach ($changes as $field => $diff) {
            Log::info('courier_runtime_repair_write', [
                'user_id' => $userId,
                'courier_id' => $courierId,
                'field' => $field,
                'change' => $diff,
                'field_changes' => [$field => $diff],
                'had_active_order' => $hadActiveOrder,
                'courier_status' => $courierStatus,
                'source_context' => $sourceContext,
                'counter' => 'courier_runtime_repair_writes_total',
                'counter_increment' => 1,
                'counter_labels' => [
                    'field' => $field,
                    'repair_type' => $repairType,
                    'repair_reason' => $repairReason,
                ],
            ]);
        }
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return $value;
    }
}
