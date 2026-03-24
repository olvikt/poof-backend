<?php

namespace App\Services\Geocoding;

class GeocodeTextNormalizer
{
    public function buildSearchQueryProfile(string $query): array
    {
        $primary = $this->normalizeSearchText($query);
        $variants = [$primary];
        $fallback = $this->buildUkrainianAddressFallback($primary);

        if ($fallback !== null && $fallback !== $primary) {
            $variants[] = $fallback;
        }

        return [
            'primary' => $primary,
            'variants' => array_values(array_unique(array_filter($variants))),
            'cache_key' => implode('|', array_values(array_unique(array_filter($variants)))),
            'contains_ru_address_signals' => $this->containsRussianAddressSignals($primary),
        ];
    }

    public function parseSearchQueryProfile(array $queryProfile): array
    {
        $base = $this->parseSearchQuery((string) ($queryProfile['primary'] ?? ''));
        $variants = [];

        foreach ($queryProfile['variants'] ?? [] as $variant) {
            $variant = $this->normalizeSearchText((string) $variant);

            if ($variant === '') {
                continue;
            }

            $variants[] = $this->parseSearchQuery($variant);
        }

        $base['variants'] = $variants;
        $base['contains_ru_address_signals'] = (bool) ($queryProfile['contains_ru_address_signals'] ?? false);

        return $base;
    }

    public function normalizeSearchText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[,.]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    public function tokenizeSearchText(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(preg_split('/[^\pL\pN\/-]+/u', $value) ?: [], function (string $token) {
            return $token !== '';
        }));
    }

    public function containsRussianAddressSignals(string $query): bool
    {
        if ($query === '') {
            return false;
        }

        return preg_match('/[ыэёъ]/u', $query) === 1
            || preg_match('/(ская|ского|ский|ческ|ическ|иковск|овск|евск)/u', $query) === 1
            || (preg_match('/\p{Cyrillic}/u', $query) === 1 && preg_match('/и/u', $query) === 1 && preg_match('/[іїєґ]/u', $query) !== 1);
    }

    private function parseSearchQuery(string $query): array
    {
        $normalized = $this->normalizeSearchText($query);
        preg_match('/(?<!\pL)(\d{1,5}[\pL\/-]?)(?!\pL)/u', $normalized, $houseMatches);
        $house = isset($houseMatches[1]) ? trim($houseMatches[1]) : null;
        $tokens = $this->tokenizeSearchText($normalized);
        $streetTokens = array_values(array_filter($tokens, function (string $token) use ($house) {
            return $house === null || $token !== $house;
        }));

        return [
            'normalized' => $normalized,
            'tokens' => $tokens,
            'street_tokens' => $streetTokens,
            'house' => $house,
            'has_house_intent' => $house !== null,
        ];
    }

    private function buildUkrainianAddressFallback(string $query): ?string
    {
        if ($query === '' || ! preg_match('/\p{Cyrillic}/u', $query) || ! $this->containsRussianAddressSignals($query)) {
            return null;
        }

        $converted = strtr($query, [
            'ё' => 'йо',
            'ы' => 'и',
            'э' => 'е',
            'ъ' => '',
        ]);

        $patterns = [
            '/овская\b/u' => 'івська',
            '/евская\b/u' => 'івська',
            '/ская\b/u' => 'ська',
            '/ского\b/u' => 'ського',
            '/ский\b/u' => 'ський',
            '/ческих\b/u' => 'чних',
            '/ческая\b/u' => 'чна',
            '/ческое\b/u' => 'чне',
            '/ческий\b/u' => 'чний',
            '/ческ\b/u' => 'чн',
            '/ическая\b/u' => 'ічна',
            '/ический\b/u' => 'ічний',
            '/ическое\b/u' => 'ічне',
            '/иковская\b/u' => 'иківська',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $converted = preg_replace($pattern, $replacement, $converted) ?? $converted;
        }

        $converted = preg_replace('/(?<!р)и/u', 'і', $converted) ?? $converted;

        $converted = preg_replace('/\s+/u', ' ', $converted) ?? $converted;
        $converted = trim($converted);

        return $converted !== '' ? $converted : null;
    }
}
