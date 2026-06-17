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
            'dropoff_stop' => ['required', 'string'],
            'route_id' => ['required', 'string'],
            'passenger_count' => ['required', 'integer', 'min:1'],
            'preferred_time' => ['required', 'string', 'in:now,schedule'],
        ];
    }
}
