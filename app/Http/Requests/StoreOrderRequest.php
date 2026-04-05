<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isClient();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['one_time', 'subscription'])],
            'service' => ['required', 'string', 'max:64'],
            'service_mode' => ['nullable', Rule::in(['asap', 'preferred_window'])],
            'client_wait_preference' => ['nullable', Rule::in(['auto_cancel_if_not_found', 'allow_late_fulfillment'])],
            'promise_consent' => ['nullable', 'boolean'],

            'bags_count' => ['required', 'integer', 'min:1', 'max:10'],
            'total_weight_kg' => ['required', 'numeric', 'min:0.1', 'max:12'],

            'scheduled_date' => ['required_if:service_mode,preferred_window', 'nullable', 'date'],
            'time_from' => ['required_if:service_mode,preferred_window', 'nullable', 'date_format:H:i'],
            'time_to' => ['required_if:service_mode,preferred_window', 'nullable', 'date_format:H:i', 'after:time_from'],

            'address_id' => [
                'nullable',
                Rule::exists('client_addresses', 'id')
                    ->where('user_id', (int) $this->user()?->id),
            ],

            'comment' => ['nullable', 'string', 'max:500'],

            // Explicitly reject legacy payload aliases to avoid silent fallback behavior.
            'order_type' => ['prohibited'],
            'scheduled_time_from' => ['prohibited'],
            'scheduled_time_to' => ['prohibited'],
            'address' => ['prohibited'],
            'address_text' => ['prohibited'],
            'handover_type' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'total_weight_kg.max' => 'Максимальна вага одного виносу — 12 кг',
            'time_to.after' => 'Кінець інтервалу має бути після початку',
            'promise_consent.accepted' => 'Підтвердьте умови виконання замовлення.',
        ];
    }
}
