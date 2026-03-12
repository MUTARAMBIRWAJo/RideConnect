<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MobileLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Can be email or phone for Flutter clients.
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Email or phone is required.',
            'password.required' => 'Password is required.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        // Backward compatibility with old payload shape.
        if (!$this->filled('login') && $this->filled('email')) {
            $payload['login'] = $this->input('email');
        }

        if (!$this->filled('login') && $this->filled('phone')) {
            $payload['login'] = $this->input('phone');
        }

        if (!empty($payload)) {
            $this->merge($payload);
        }
    }
}
