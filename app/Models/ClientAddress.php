<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ClientAddress extends Model
{
    private const CLIENT_WRITE_FIELDS = [
        'label',
        'title',
        'building_type',
        'address_text',
        'city',
        'region',
        'street',
        'house',
        'entrance',
        'intercom',
        'floor',
        'apartment',
        'lat',
        'lng',
        'place_id',
        'geocode_source',
        'geocode_accuracy',
        'geocoded_at',
        'is_default',
    ];

    /* =========================================================
     |  MASS ASSIGNMENT
     | ========================================================= */

    protected $fillable = [
        'user_id',

        // тип / название
        'label',        // home | work | custom
        'title',        // "Дім", "Офіс", "Мама"

        // опциональные поля адреса
        'city',
        'region',
        'street',
        'house',
        'building_type',

        // отображение (НЕ источник истины)
        'address_text',

        // детали
        'entrance',
        'intercom',
        'floor',
        'apartment',

        // координаты (ИСТИНА)
        'lat',
        'lng',
        'place_id',

        // гео-мета
        'geocode_source',   // places | gps | manual
        'geocode_accuracy', // rooftop | street | city
        'geocoded_at',

        // флаги
        'is_default',
    ];

    /* =========================================================
     |  CASTS
     | ========================================================= */

    protected $casts = [
        'is_default'  => 'boolean',
        'lat'         => 'float',
        'lng'         => 'float',
        'geocoded_at' => 'datetime',
    ];

    public static function createForUser(int $userId, array $attributes): self
    {
        $payload = Arr::only($attributes, self::CLIENT_WRITE_FIELDS);
        $payload['user_id'] = $userId;

        return self::query()->create($payload);
    }

    public function updateFromClient(array $attributes): void
    {
        $this->fill(Arr::only($attributes, self::CLIENT_WRITE_FIELDS));
        $this->save();
    }

    /* =========================================================
     |  RELATIONS
     | ========================================================= */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
	
	/**
	 * Заказы, оформленные с этого адреса
	 */
	public function orders()
	{
		return $this->hasMany(Order::class, 'address_id');
	}

    /* =========================================================
     |  SCOPES
     | ========================================================= */

    /**
     * Активные адреса (имеют координаты)
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNotNull('lat')
            ->whereNotNull('lng');
    }

    /* =========================================================
     |  ACCESSORS (UI)
     | ========================================================= */

    /**
     * Подпись типа адреса
     */
    public function getLabelTitleAttribute(): string
    {
        return match ($this->label) {
            'home'  => 'Дім',
            'work'  => 'Робота',
            default => $this->title ?: 'Інше',
        };
    }

    /**
     * Полный адрес для отображения
     */
    public function getFullAddressAttribute(): string
    {
        if ($this->address_text) {
            return $this->address_text;
        }

        $parts = array_filter([
            $this->city,
            trim("{$this->street} {$this->house}") ?: null,
            $this->entrance  ? 'підʼїзд ' . $this->entrance  : null,
            $this->floor     ? 'поверх ' . $this->floor     : null,
            $this->apartment ? 'кв. '     . $this->apartment : null,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Адрес подтверждён (ТОП-логика)
     */
    public function getIsVerifiedAttribute(): bool
    {
        return ! is_null($this->lat)
            && ! is_null($this->lng)
            && ! is_null($this->geocoded_at);
    }

    /* =========================================================
     |  HELPERS
     | ========================================================= */

    /**
     * Пересобрать address_text из street + house
     * (позже будет перезаписываться из Google formatted_address)
     */
    public function rebuildAddressText(): void
    {
        if ($this->address_text) {
            return;
        }

        $this->address_text = trim(
            collect([$this->street, $this->house])
                ->filter()
                ->implode(' ')
        );
    }

    public function markAsDefaultForUser(): void
    {
        DB::transaction(function (): void {
            self::query()
                ->where('user_id', $this->user_id)
                ->update(['is_default' => false]);

            self::query()
                ->whereKey($this->getKey())
                ->where('user_id', $this->user_id)
                ->update(['is_default' => true]);
        });
    }

    /* =========================================================
     |  MODEL EVENTS
     | ========================================================= */

    protected static function booted(): void
    {
        static::saving(function (self $address) {
            $address->rebuildAddressText();
        });
    }
}
