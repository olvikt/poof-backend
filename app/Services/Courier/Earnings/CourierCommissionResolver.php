<?php

declare(strict_types=1);

namespace App\Services\Courier\Earnings;

use App\Models\CourierEarningSetting;

class CourierCommissionResolver
{
    public function globalRatePercent(): float
    {
        return (float) CourierEarningSetting::current()->global_commission_rate_percent;
    }
}
