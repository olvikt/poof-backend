<?php

namespace App\Livewire\Client;

use Livewire\Component;
use App\Models\ClientAddress;
use Illuminate\Support\Collection;

class AddressManager extends Component
{
    public Collection $addresses;

    public ?int $deleteId = null;
	
	public ?int $actionsId = null;
	
	public ?ClientAddress $actionsAddress = null;
	

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
	
	/** ---------- Actions sheet ---------- */

	public function openActions(int $id): void
	{
		$this->actionsId = $id;

		$this->actionsAddress = ClientAddress::where('id', $id)
			->where('user_id', auth()->id())
			->first();

		$this->dispatch('sheet:open', name: 'addressActions');
	}

	public function closeActions(): void
	{
		$this->actionsId = null;
		$this->actionsAddress = null;

		$this->dispatch('sheet:close', name: 'addressActions');
	}

	public function actionEdit(): void
	{
		if (! $this->actionsId) return;

		$id = $this->actionsId;
		$this->closeActions();

		$this->edit($id);
	}

	public function actionSetDefault(): void
	{
		if (! $this->actionsId) return;

		$id = $this->actionsId;

		$this->setDefault($id);

		$this->closeActions();
	}

	public function actionDelete(): void
	{
		if (! $this->actionsId) return;

		$id = $this->actionsId;

		$this->closeActions();

		$this->confirmDelete($id);
	}
	
	
	public function orderFromAddress(): void
	{
		if (! $this->actionsId) {
			return;
		}

		$addressId = $this->actionsId;

		// закрываем меню действий
		$this->closeActions();

		// редирект на создание заказа с адресом
		$this->redirect(
			route('client.order.create', ['address_id' => $addressId]),
			navigate: true
		);
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
