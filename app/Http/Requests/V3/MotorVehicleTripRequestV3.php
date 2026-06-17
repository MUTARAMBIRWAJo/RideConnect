<?php

namespace App\Http\Requests\V3;

use Illuminate\Foundation\Http\FormRequest;

class MotorVehicleTripRequestV3 extends FormRequest
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
            'ride_mode' => ['required', 'string', 'in:instant,scheduled'],
            'payment_method' => ['required', 'string'],
        ];
    }
}
