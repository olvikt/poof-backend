<?php

declare(strict_types=1);

namespace App\Support\Orders;

use Illuminate\Support\Carbon;
use Throwable;

class LegacyScheduleNormalizer
{
    public function resolveWindowFromLegacy(?string $scheduledDate, ?string $fromValue, ?string $toValue, int $fallbackHours = 2): array
    {
        $from = $this->resolveDateTimePoint($scheduledDate, $fromValue);

        if (! $from instanceof Carbon) {
            return [null, null];
        }

        $to = $this->resolveDateTimePoint($scheduledDate, $toValue);

        if (! $to instanceof Carbon || $to->lessThanOrEqualTo($from)) {
            $to = $from->copy()->addHours($fallbackHours);
        }

        return [$from, $to];
    }

    public function resolveDateTimePoint(?string $scheduledDate, ?string $value): ?Carbon
    {
        $scheduledDate = $this->normalizeString($scheduledDate);
        $value = $this->normalizeString($value);

        if ($value !== null && $this->looksLikeDateTime($value)) {
            return $this->parseDateTime($value);
        }

        if ($scheduledDate === null) {
            return null;
        }

        $base = $this->parseDateTimeOrDate($scheduledDate);

        if (! $base instanceof Carbon) {
            return null;
        }

        if ($value === null) {
            return $this->looksLikeDateOnly($scheduledDate) ? null : $base;
        }

        if (! $this->looksLikeDateOnly($scheduledDate)) {
            return $base;
        }

        $normalizedTime = $this->normalizeTime($value);

        if ($normalizedTime === null) {
            return null;
        }

        return $base->copy()->setTimeFromTimeString($normalizedTime);
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function looksLikeDateOnly(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }

    private function looksLikeDateTime(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{1,2}:\d{2}(:\d{2})?(?:[+-]\d{2}:\d{2}|Z)?$/', $value);
    }

    private function normalizeTime(string $value): ?string
    {
        if (! preg_match('/^(?<h>\d{1,2}):(?<m>\d{2})(?::(?<s>\d{2}))?$/', $value, $matches)) {
            return null;
        }

        $hours = (int) $matches['h'];
        $minutes = (int) $matches['m'];
        $seconds = isset($matches['s']) ? (int) $matches['s'] : 0;

        if ($hours > 23 || $minutes > 59 || $seconds > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    private function parseDateTimeOrDate(string $value): ?Carbon
    {
        if ($this->looksLikeDateOnly($value)) {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        }

        return $this->parseDateTime($value);
    }

    private function parseDateTime(string $value): ?Carbon
    {
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\\TH:i:sP',
            'Y-m-d\\TH:iP',
            'Y-m-d\\TH:i:s',
            'Y-m-d\\TH:i',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);

                if ($parsed instanceof Carbon) {
                    return $parsed;
                }
            } catch (Throwable) {
                // Try next format.
            }
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
