<?php

namespace App\Livewire\Client;

use Livewire\Component;
use App\Models\ClientAddress;
use Illuminate\Support\Collection;

class AddressManager extends Component
{
    public Collection $addresses;

    public ?int $deleteId = null;

    protected $listeners = [
        'address-saved' => 'reloadAddresses',
    ];

    public function mount(): void
    {
        $this->reloadAddresses();
    }

    public function reloadAddresses(): void
    {
        $userId = auth()->id();

        $this->addresses = ClientAddress::where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get();
    }

    public function create(): void
    {
        $this->dispatch('address:open', addressId: null);
    }

    public function edit(int $id): void
    {
        $this->dispatch('address:open', addressId: $id);
    }

    public function setDefault(int $id): void
    {
        $userId = auth()->id();

        ClientAddress::where('user_id', $userId)->update(['is_default' => false]);
        ClientAddress::where('id', $id)->where('user_id', $userId)->update(['is_default' => true]);

        $this->reloadAddresses();
    }

    /** ---------- Delete flow ---------- */

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->dispatch('sheet:open', name: 'deleteAddress');
    }

    public function cancelDelete(): void
    {
        $this->deleteId = null;
        $this->dispatch('sheet:close', name: 'deleteAddress');
    }

    public function deleteConfirmed(): void
    {
        if (! $this->deleteId) {
            return;
        }

        $userId = auth()->id();

        $address = ClientAddress::where('id', $this->deleteId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $wasDefault = (bool) $address->is_default;

        $address->delete();

        if ($wasDefault) {
            ClientAddress::where('user_id', $userId)
                ->orderByDesc('updated_at')
                ->limit(1)
                ->update(['is_default' => true]);
        }

        $this->deleteId = null;

        $this->dispatch('sheet:close', name: 'deleteAddress');

        $this->reloadAddresses();
    }

    public function render()
    {
        return view('livewire.client.address-manager');
    }
}
