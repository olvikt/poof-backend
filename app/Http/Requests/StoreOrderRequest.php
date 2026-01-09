<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isClient();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:one_time,subscription'],
            'service' => ['required', 'string'],

            'bags_count' => ['required', 'integer', 'min:1', 'max:10'],
            'total_weight_kg' => ['required', 'numeric', 'min:0.1', 'max:12'],

            'scheduled_date' => ['required', 'date'],
            'time_from' => ['required', 'date_format:H:i'],
            'time_to' => ['required', 'date_format:H:i', 'after:time_from'],

            'address_id' => ['nullable', 'exists:client_addresses,id'],

            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'total_weight_kg.max' => 'Максимальна вага одного виносу — 12 кг',
            'time_to.after' => 'Кінець інтервалу має бути після початку',
        ];
    }
}
