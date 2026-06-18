<?php

namespace App\Http\Requests\V3;

use Illuminate\Foundation\Http\FormRequest;

class PublicBusTripRequestV3 extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pickup_stop' => ['required', 'string'],
            'pickup_lat' => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng' => ['required', 'numeric', 'between:-180,180'],
            'dropoff_stop' => ['required', 'string'],
            'dropoff_lat' => ['required', 'numeric', 'between:-90,90'],
            'dropoff_lng' => ['required', 'numeric', 'between:-180,180'],
            'route_id' => ['required', 'string'],
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'passenger_count' => ['required', 'integer', 'min:1'],
            'preferred_time' => ['required', 'string', 'in:now,schedule'],
        ];
    }
}
