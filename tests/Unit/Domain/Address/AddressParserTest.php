<?php

namespace Tests\Unit\Domain\Address;

use App\Domain\Address\AddressParser;
use PHPUnit\Framework\TestCase;

class AddressParserTest extends TestCase
{
    public function test_it_normalizes_search_payloads(): void
    {
        $parser = new AddressParser();

        $this->assertSame('Main Street, Kyiv', $parser->normalizeSearch(' Main Street, Kyiv '));
        $this->assertSame('Label', $parser->normalizeSearch(['label' => ' Label ']));
    }

    public function test_it_parses_houses_and_street_tokens(): void
    {
        $parser = new AddressParser();

        $this->assertSame('Main Street', $parser->normalizeStreet('12, Main Street'));
        $this->assertSame('108 к5', $parser->normalizeHouse('108 корпус 5'));
        $this->assertNull($parser->normalizeHouse('Корпус А'));
    }

    public function test_it_extracts_street_and_city_from_search_parts(): void
    {
        $parser = new AddressParser();

        [$street, $city] = $parser->extractStreetAndCityFromSearch(' Main Street , Kyiv , Ukraine ');

        $this->assertSame('Main Street', $street);
        $this->assertSame('Kyiv', $city);
    }
}
