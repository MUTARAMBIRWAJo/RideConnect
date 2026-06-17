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
            'dropoff_location' => ['required', 'string'],
            'car_type_preference' => ['required', 'string', 'in:economy,comfort,luxury'],
            'scheduled_time' => ['nullable', 'date'],
        ];
    }
}
