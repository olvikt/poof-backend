<?php

declare(strict_types=1);

namespace App\Actions\Courier\Payout;

use App\Models\CourierPayoutRequisite;
use App\Models\User;

class SaveCourierPayoutRequisitesAction
{
    public function execute(
        User $courier,
        string $cardHolderName,
        string $cardNumber,
        ?string $bankName = null,
        ?string $notes = null,
    ): CourierPayoutRequisite {
        $digitsOnlyCardNumber = preg_replace('/\D+/', '', $cardNumber) ?? '';

        $maskedCardNumber = '**** **** **** '.substr($digitsOnlyCardNumber, -4);

        return CourierPayoutRequisite::query()->updateOrCreate(
            ['courier_id' => $courier->id],
            [
                'card_holder_name' => trim($cardHolderName),
                'card_number_encrypted' => $digitsOnlyCardNumber,
                'masked_card_number' => $maskedCardNumber,
                'bank_name' => $bankName !== null ? trim($bankName) : null,
                'notes' => $notes !== null ? trim($notes) : null,
            ],
        );
    }
}
