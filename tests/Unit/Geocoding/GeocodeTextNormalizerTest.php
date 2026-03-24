<?php

namespace Tests\Unit\Geocoding;

use App\Services\Geocoding\GeocodeTextNormalizer;
use Tests\TestCase;

class GeocodeTextNormalizerTest extends TestCase
{
    public function test_it_normalizes_text_and_tokenizes_search_query(): void
    {
        $normalizer = new GeocodeTextNormalizer();

        $normalized = $normalizer->normalizeSearchText('  ВУЛ.,   Мандриківська,  173-Б  ');

        $this->assertSame('вул мандриківська 173-б', $normalized);
        $this->assertSame(['вул', 'мандриківська', '173-б'], $normalizer->tokenizeSearchText($normalized));
    }

    public function test_it_builds_query_profile_with_ru_to_ua_fallback_variant_and_cache_key(): void
    {
        $normalizer = new GeocodeTextNormalizer();

        $profile = $normalizer->buildSearchQueryProfile('  Сичеславская 12  ');

        $this->assertSame('сичеславская 12', $profile['primary']);
        $this->assertSame(['сичеславская 12', 'січеславська 12'], $profile['variants']);
        $this->assertSame('сичеславская 12|січеславська 12', $profile['cache_key']);
        $this->assertTrue($profile['contains_ru_address_signals']);
    }

    public function test_it_parses_query_profile_into_tokens_house_intent_and_variants(): void
    {
        $normalizer = new GeocodeTextNormalizer();

        $profile = [
            'primary' => 'Мандриківська 173',
            'variants' => ['Мандриківська 173', 'Мандрыковская 173'],
            'contains_ru_address_signals' => false,
        ];

        $parsed = $normalizer->parseSearchQueryProfile($profile);

        $this->assertSame('мандриківська 173', $parsed['normalized']);
        $this->assertSame(['мандриківська', '173'], $parsed['tokens']);
        $this->assertSame(['мандриківська'], $parsed['street_tokens']);
        $this->assertSame('173', $parsed['house']);
        $this->assertTrue($parsed['has_house_intent']);
        $this->assertCount(2, $parsed['variants']);
    }

    public function test_it_detects_ru_orthography_signals_only_when_present(): void
    {
        $normalizer = new GeocodeTextNormalizer();

        $this->assertTrue($normalizer->containsRussianAddressSignals('сичеславская набережная'));
        $this->assertFalse($normalizer->containsRussianAddressSignals('січеславська набережна'));
        $this->assertFalse($normalizer->containsRussianAddressSignals(''));
    }
}
