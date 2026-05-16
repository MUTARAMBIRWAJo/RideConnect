<?php

namespace Database\Factories;

use App\Models\MobileUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MobileUser>
 */
class MobileUserFactory extends Factory
{
    protected $model = MobileUser::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => $this->faker->randomElement(['DRIVER', 'PASSENGER']),
            'profile_photo' => null,
            'is_verified' => true,
        ];
    }

    /**
     * Indicate that the mobile user is unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => false,
        ]);
    }
}
