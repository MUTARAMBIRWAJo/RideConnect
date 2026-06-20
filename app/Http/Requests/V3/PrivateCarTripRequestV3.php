<?php

namespace App\Http\Requests\V3;

use Illuminate\Foundation\Http\FormRequest;

class PrivateCarTripRequestV3 extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pickup_location' => ['required', 'string'],
            'pickup_lat' => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng' => ['required', 'numeric', 'between:-180,180'],
            'dropoff_location' => ['required', 'string'],
            'dropoff_lat' => ['required', 'numeric', 'between:-90,90'],
            'dropoff_lng' => ['required', 'numeric', 'between:-180,180'],
            'car_type_preference' => ['required', 'string'],
            'scheduled_time' => ['nullable', 'date'],
            'requested_seats' => ['nullable', 'integer', 'min:1', 'max:4'],
        ];
    }
}
