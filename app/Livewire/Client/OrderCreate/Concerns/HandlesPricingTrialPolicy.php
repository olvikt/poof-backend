<?php

namespace App\Livewire\Client\OrderCreate\Concerns;

use App\Models\Order;
use App\Domain\Address\Precision;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

trait HandlesPricingTrialPolicy
{
    public function selectBags(int $count): void
    {
        $availableCounts = array_keys($this->bagPricingOptions);
        $count = (int) $count;

        if (! in_array($count, $availableCounts, true)) {
            return;
        }

        $this->bags_count = $count;

        if ($this->is_trial) {
            $this->disableTrial();
            return;
        }

        $this->recalculatePrice();
    }

    public function selectTrial(int $days): void
    {
        if ($days !== 1) {
            return;
        }

        if ($this->trial_used) {
            $this->showTrialBlockedModal = true;
            return;
        }

        $this->is_trial = true;
        $this->trial_days = 1;
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

    public function openSubscriptionModal(): void
    {
        $this->showSubscriptionModal = true;
    }

    public function selectSubscriptionPlan(string $frequency): void
    {
        if (! in_array($frequency, ['every_3_days', 'daily'], true)) {
            return;
        }

        $this->subscription_frequency = $frequency;
        $this->showSubscriptionModal = false;
    }

    protected function recalculatePrice(): void
    {
        if ($this->is_trial) {
            $this->price = 0;
            return;
        }

        try {
            $this->price = (int) Order::calcPriceByBags($this->bags_count);
            $this->resetErrorBag('bags_count');
        } catch (ValidationException $e) {
            $this->price = 0;
            $this->addError('bags_count', $e->errors()['bags_count'][0] ?? 'Тариф недоступний.');
        }
    }

    protected function validateCoordinatesOrFail(): void
    {
        if (is_null($this->lat) || is_null($this->lng)) {
            $this->addError('address_text', 'Вкажіть адресу або точку на мапі.');
            return;
        }

        if (Precision::fromNullable($this->address_precision)->isApprox() && ! $this->coordsFromAddressBook) {
            $this->addError('address_text', 'Будь ласка, уточніть точку на мапі.');
            return;
        }

        $this->resetErrorBag('address_text');
    }
}
