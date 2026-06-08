<?php

namespace App\Http\Requests\Passenger;

use Illuminate\Foundation\Http\FormRequest;

class CreatePublicBusTripRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isPassenger() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'corridor_id' => ['required', 'integer', 'exists:transport_corridors,id'],
            'pickup_location' => ['required', 'string', 'min:3', 'max:255'],
            'dropoff_location' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! empty($this->all())) {
            return;
        }

        $rawContent = $this->getContent();
        if (! is_string($rawContent) || trim($rawContent) === '') {
            return;
        }

        $decoded = json_decode($rawContent, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return;
        }

        $this->merge($decoded);
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'corridor_id.required' => 'Corridor ID is required',
            'corridor_id.exists' => 'The selected corridor does not exist',
            'pickup_location.required' => 'Pickup location name is required',
            'pickup_location.min' => 'Pickup location must be at least 3 characters',
            'dropoff_location.required' => 'Dropoff location name is required',
            'dropoff_location.min' => 'Dropoff location must be at least 3 characters',
        ];
    }
}
