<?php

namespace App\Http\Requests\Api;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            // Only allow passenger and rider via public API
            'role' => ['required', 'string', 'in:' . implode(',', UserRole::mobileUserRoles())],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'role.in' => 'Only passenger and driver roles are allowed for API registration.',
            'email.unique' => 'This email is already registered.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert role to uppercase to match enum
        if ($this->has('role')) {
            $this->merge([
                'role' => strtoupper($this->role),
            ]);
        }
    }
}
