<?php

namespace App\Support\Auth;

final class PhoneNormalizer
{
    public static function normalize(?string $phone, ?string $countryCode = null): string
    {
        $normalizedPhone = preg_replace('/\D/', '', (string) $phone) ?? '';
        $normalizedCountryCode = preg_replace('/\D/', '', (string) $countryCode) ?? '';

        if ($normalizedCountryCode === '' || str_starts_with($normalizedPhone, $normalizedCountryCode)) {
            return $normalizedPhone;
        }

        return $normalizedCountryCode.$normalizedPhone;
    }
}
