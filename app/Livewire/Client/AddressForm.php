<?php

namespace App\Livewire\Client;

use Livewire\Component;

class AddressForm extends Component
{
    public ?string $address = null;
    public ?string $city = null;
    public ?string $entrance = null;
    public ?string $floor = null;
    public ?string $apartment = null;

    public function mount()
    {
        $addr = auth()->user()->address;

        if ($addr) {
            $this->address   = $addr->address;
            $this->city      = $addr->city;
            $this->entrance  = $addr->entrance;
            $this->floor     = $addr->floor;
            $this->apartment = $addr->apartment;
        }
    }

    protected $rules = [
        'address'   => 'required|string|min:5',
        'city'      => 'nullable|string',
        'entrance'  => 'nullable|string|max:10',
        'floor'     => 'nullable|string|max:10',
        'apartment' => 'nullable|string|max:10',
    ];

public function save()
{
    $this->validate();

    auth()->user()->address()->updateOrCreate(
        ['user_id' => auth()->id()], // 🔑 КЛЮЧ
        [
            'address'   => $this->address,
            'city'      => $this->city,
            'entrance'  => $this->entrance,
            'floor'     => $this->floor,
            'apartment' => $this->apartment,
        ]
    );

    $this->dispatch('sheet:close');

    $this->dispatch('address-saved', address: $this->address);
}

    public function render()
    {
        return view('livewire.client.address-form');
    }
}
