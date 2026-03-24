<?php

namespace Tests\Unit\Address;

use App\DTO\Address\PersistAddressData;
use Tests\TestCase;

class PersistAddressDataTest extends TestCase
{
    public function test_to_array_returns_canonical_payload_as_is(): void
    {
        $canonicalPayload = [
            'label' => 'home',
            'title' => 'My place',
            'street' => 'Main Street',
            'house' => '7A',
            'entrance' => '0',
            'apartment' => '',
            'intercom' => null,
        ];

        $dto = PersistAddressData::fromCanonical($canonicalPayload);

        $this->assertSame($canonicalPayload, $dto->toArray());
    }

    public function test_with_user_id_enforces_authenticated_user_precedence(): void
    {
        $dto = PersistAddressData::fromCanonical([
            'user_id' => 999,
            'label' => 'home',
            'street' => 'Main Street',
            'house' => '7A',
        ]);

        $payload = $dto->withUserId(123);

        $this->assertSame(123, $payload['user_id']);
        $this->assertSame('home', $payload['label']);
        $this->assertSame('Main Street', $payload['street']);
        $this->assertSame('7A', $payload['house']);
    }

    public function test_with_user_id_adds_user_id_when_missing_in_canonical_payload(): void
    {
        $dto = PersistAddressData::fromCanonical([
            'label' => 'work',
            'street' => 'Boundary Street',
            'house' => '42',
        ]);

        $payload = $dto->withUserId(321);

        $this->assertSame([
            'label' => 'work',
            'street' => 'Boundary Street',
            'house' => '42',
            'user_id' => 321,
        ], $payload);
    }

    public function test_with_user_id_only_mutates_user_id_key_and_keeps_other_keys_unchanged(): void
    {
        $canonicalPayload = [
            'user_id' => 111,
            'label' => 'home',
            'street' => 'Main Street',
            'house' => '7A',
            'lat' => 50.45,
            'lng' => 30.52,
            'is_default' => true,
            'entrance' => '0',
            'apartment' => '',
            'intercom' => null,
        ];

        $dto = PersistAddressData::fromCanonical($canonicalPayload);

        $payload = $dto->withUserId(222);

        $expectedPayload = $canonicalPayload;
        $expectedPayload['user_id'] = 222;

        $this->assertSame($expectedPayload, $payload);
        $this->assertSame($canonicalPayload, $dto->toArray());
    }
}
