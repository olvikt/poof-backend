<?php

namespace App\Livewire\Client\OrderCreate\Concerns;

use App\Models\Order;
use App\Support\Address\AddressPrecision;
use Illuminate\Support\Facades\Auth;

trait HandlesPricingTrialPolicy
{
    public function selectBags(int $count): void
    {
        $this->bags_count = max(1, min(3, (int) $count));

        if ($this->is_trial) {
            $this->disableTrial();
            return;
        }

        $this->recalculatePrice();
    }

    public function selectTrial(int $days): void
    {
        if ($this->trial_used) {
            $this->showTrialBlockedModal = true;
            return;
        }

        $this->is_trial = true;
        $this->trial_days = in_array($days, [1, 3], true) ? (int) $days : 1;
        $this->bags_count = 1;
        $this->price = 0;
    }

    protected function userAlreadyUsedTrial(): bool
    {
        return Order::query()
            ->where('client_id', Auth::id())
            ->where('is_trial', true)
            ->exists();
    }

    public function disableTrial(): void
    {
        $this->is_trial = false;
        $this->trial_days = 1;
        $this->recalculatePrice();
    }

    protected function recalculatePrice(): void
    {
        $this->price = $this->is_trial
            ? 0
            : (int) Order::calcPriceByBags($this->bags_count);
    }

    protected function validateCoordinatesOrFail(): void
    {
        if (is_null($this->lat) || is_null($this->lng)) {
            $this->addError('address_text', 'Вкажіть адресу або точку на мапі.');
            return;
        }

        if (AddressPrecision::fromNullable($this->address_precision)->isApprox() && ! $this->coordsFromAddressBook) {
            $this->addError('address_text', 'Будь ласка, уточніть точку на мапі.');
            return;
        }

        $this->resetErrorBag('address_text');
    }
}
