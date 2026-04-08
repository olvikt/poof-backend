<?php

declare(strict_types=1);

namespace App\DTO\Courier\Profile;

final class CourierProfileUpdateData
{
    public function __construct(
        public readonly string $name,
        public readonly string $phone,
        public readonly string $email,
        public readonly string $residenceAddress,
    ) {
    }

    public function toUserAttributes(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'residence_address' => $this->residenceAddress,
        ];
    }
}
