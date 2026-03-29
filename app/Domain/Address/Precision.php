<?php

namespace App\Domain\Address;

enum Precision: string
{
    case None = 'none';
    case Approx = 'approx';
    case Exact = 'exact';

    public static function fromNullable(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::None;
    }

    public static function fromCoordinates(?float $lat, ?float $lng, bool $isExact = false): self
    {
        if ($lat === null || $lng === null) {
            return self::None;
        }

        return $isExact ? self::Exact : self::Approx;
    }

    public function isNone(): bool
    {
        return $this === self::None;
    }

    public function isApprox(): bool
    {
        return $this === self::Approx;
    }

    public function isExact(): bool
    {
        return $this === self::Exact;
    }
}
