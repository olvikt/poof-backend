<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'frequency_type',
        'pickups_per_month',
        'monthly_price',
        'max_bags',
        'max_weight_kg',
        'description',
        'ui_badge',
        'ui_subtitle',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'pickups_per_month' => 'int',
        'monthly_price' => 'int',
        'max_bags' => 'int',
        'max_weight_kg' => 'int',
        'is_active' => 'bool',
        'sort_order' => 'int',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function clientSubscriptions(): HasMany
    {
        return $this->hasMany(ClientSubscription::class, 'subscription_plan_id');
    }

    public function referenceSinglePickupPrice(): int
    {
        return (int) (BagPricing::activeOptionsMap()[1] ?? 0);
    }

    public function referenceMonthlyTotal(): int
    {
        return $this->referenceSinglePickupPrice() * max(0, (int) $this->pickups_per_month);
    }

    public function economyAmount(): int
    {
        return $this->referenceMonthlyTotal() - (int) $this->monthly_price;
    }

    public function economyPercent(): int
    {
        $referenceMonthlyTotal = $this->referenceMonthlyTotal();

        if ($referenceMonthlyTotal <= 0) {
            return 0;
        }

        return (int) round(($this->economyAmount() / $referenceMonthlyTotal) * 100);
    }

    public function approxPricePerPickup(): int
    {
        $pickupsPerMonth = max(1, (int) $this->pickups_per_month);

        return (int) round(((int) $this->monthly_price) / $pickupsPerMonth);
    }
}
