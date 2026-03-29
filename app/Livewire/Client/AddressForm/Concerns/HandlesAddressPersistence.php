<?php

namespace App\Livewire\Client\AddressForm\Concerns;

use App\Actions\Address\PersistClientAddressAction;
use App\DTO\Address\AddressFormData;
use App\DTO\Address\PersistAddressData;
use App\Services\Address\FilterClientAddressPayload;
use App\Services\Address\PrepareAddressSavePayload;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

trait HandlesAddressPersistence
{
    protected function rules(): array
    {
        return [
            'label' => 'required|in:home,work,other',
            'title' => 'nullable|string|max:50',
            'building_type' => 'required|in:apartment,house',
            'search' => 'nullable|string|max:255',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'city' => 'required|string|max:80',
            'region' => 'nullable|string|max:120',
            'street' => 'required|string|min:2|max:120',
            'house' => 'required|string|max:20',
            'entrance' => 'required_if:building_type,apartment|nullable|string|max:10',
            'floor' => 'required_if:building_type,apartment|nullable|string|max:10',
            'intercom' => 'nullable|string|max:10',
            'apartment' => 'required_if:building_type,apartment|nullable|string|max:10',
        ];
    }

    protected function messages(): array
    {
        return [
            'entrance.required_if' => 'Вкажіть підʼїзд для квартири.',
            'floor.required_if' => 'Вкажіть поверх для квартири.',
            'apartment.required_if' => 'Вкажіть квартиру/офіс для квартири.',
        ];
    }

    public function save(): void
    {
        Log::info('ui_save_flow_started', $this->saveLogContext('address', 'before_persistence'));

        try {
            $formData = AddressFormData::fromComponent($this);
            $payloadPreparer = app(PrepareAddressSavePayload::class);

            foreach ($payloadPreparer->applyFallback($formData) as $field => $value) {
                $this->{$field} = $value;
            }

            $this->validate();
            $this->ensureCoordinatesArePresent();

            $formData = AddressFormData::fromComponent($this);
            $payload = $payloadPreparer->execute($formData);
            $filteredPayload = app(FilterClientAddressPayload::class)->execute($payload->toArray());

            app(PersistClientAddressAction::class)->execute(
                $formData,
                new PersistAddressData($filteredPayload),
                auth()->id(),
            );

            Log::info('ui_save_flow_succeeded', $this->saveLogContext('address', 'after_persistence'));

            $this->dispatch('address-saved');
            $this->dispatch('address-saved')->to('client.address-manager');
            $this->dispatch('sheet:close', name: 'addressForm');
        } catch (ValidationException $e) {
            if ($this->building_type === 'apartment' && collect(['entrance', 'floor', 'apartment'])->some(fn (string $field): bool => array_key_exists($field, $e->errors()))) {
                $this->dispatch('notify', type: 'error', message: 'Для квартири заповніть підʼїзд, поверх і квартиру.');
            }

            Log::warning('ui_save_flow_failed', $this->saveLogContext('address', 'before_persistence', [
                'failure_type' => 'validation',
                'error_fields' => array_keys($e->errors()),
            ]));

            throw $e;
        } catch (\Throwable $e) {
            report($e);

            $this->addError('search', 'Сталася помилка при збереженні. Перевірте поля та спробуйте ще раз.');

            Log::error('ui_save_flow_failed', $this->saveLogContext('address', 'after_persistence', [
                'failure_type' => 'exception',
                'exception_class' => $e::class,
            ]));
        }
    }

    protected function ensureCoordinatesArePresent(): void
    {
        if ($this->lat !== null && $this->lng !== null) {
            return;
        }

        throw ValidationException::withMessages([
            'search' => 'Уточніть точку на мапі.',
        ]);
    }

    protected function saveLogContext(string $flow, string $boundary, array $extra = []): array
    {
        return $extra + [
            'flow' => $flow,
            'boundary' => $boundary,
            'user_id' => auth()->id(),
            'address_id' => $this->addressId,
            'building_type' => $this->building_type,
            'has_coordinates' => $this->lat !== null && $this->lng !== null,
            'address_precision' => $this->addressPrecision ?? null,
        ];
    }
}
