<?php

namespace App\DTO\Profile;

use App\Livewire\Client\ProfileForm;

class ProfileFormData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $phone,
        public readonly string $email,
    ) {
    }

    public static function fromComponent(ProfileForm $component): self
    {
        return new self(
            name: $component->name,
            phone: $component->phone,
            email: $component->email,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
        ];
    }
}
