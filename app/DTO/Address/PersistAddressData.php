<?php

namespace App\DTO\Address;

class PersistAddressData
{
    public function __construct(private readonly array $attributes)
    {
    }

    public static function fromCanonical(array $canonicalPayload): self
    {
        return new self($canonicalPayload);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function withUserId(int $userId): array
    {
        return array_merge($this->attributes, ['user_id' => $userId]);
    }

}
