<?php

declare(strict_types=1);

namespace App\Services\Payments\WayForPay;

class WayForPaySignature
{
    /**
     * @param list<string|int|float> $values
     */
    public function sign(array $values, string $secret): string
    {
        return hash_hmac('md5', implode(';', $values), $secret);
    }

    /**
     * @param list<string|int|float> $values
     */
    public function verify(array $values, string $secret, string $expected): bool
    {
        return hash_equals($this->sign($values, $secret), $expected);
    }
}
