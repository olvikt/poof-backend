<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BagPricing extends Model
{
    protected $fillable = [
        'bags_count',
        'price',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'bags_count' => 'int',
        'price' => 'int',
        'is_active' => 'bool',
        'sort_order' => 'int',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('bags_count');
    }

    public static function activeOptionsMap(): array
    {
        return self::query()
            ->active()
            ->ordered()
            ->pluck('price', 'bags_count')
            ->mapWithKeys(fn ($price, $bagsCount) => [(int) $bagsCount => (int) $price])
            ->all();
    }
}
