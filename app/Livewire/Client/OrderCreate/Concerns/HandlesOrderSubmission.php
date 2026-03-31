<?php

namespace App\Livewire\Client\OrderCreate\Concerns;

use App\Actions\Orders\Create\CreateLegacyWebOrderAction;
use App\DTO\Orders\LegacyWebOrderCreatePayload;
use Illuminate\Support\Facades\Auth;

trait HandlesOrderSubmission
{
    public function submit(): void
    {
        if ($this->is_trial && $this->trial_used) {
            $this->showTrialBlockedModal = true;
            return;
        }

        $this->refreshBagPricingOptions();
        $this->validate();
        $this->validateCoordinatesOrFail();

        if ($this->getErrorBag()->has('address_text')) {
            return;
        }

        $this->recalculatePrice();

        $order = app(CreateLegacyWebOrderAction::class)->handle(
            clientId: (int) Auth::id(),
            payload: LegacyWebOrderCreatePayload::fromArray([
                'address_id' => $this->address_id,
                'address_text' => $this->address_text,
                'lat' => $this->lat,
                'lng' => $this->lng,
                'entrance' => $this->entrance,
                'floor' => $this->floor,
                'apartment' => $this->apartment,
                'intercom' => $this->intercom,
                'comment' => $this->comment,
                'scheduled_date' => $this->scheduled_date,
                'scheduled_time_from' => $this->scheduled_time_from,
                'scheduled_time_to' => $this->scheduled_time_to,
                'handover_type' => $this->handover_type,
                'bags_count' => $this->bags_count,
                'price' => $this->price,
                'promo_code' => $this->promo_code,
                'is_trial' => $this->is_trial,
                'trial_days' => $this->trial_days,
            ]),
        );

        $this->createdOrderId = $order->id;
        $this->showPaymentModal = true;

        if ($this->is_trial) {
            $this->trial_used = true;
        }
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
    }
}
