<?php

namespace Database\Factories;

use App\Models\MobileUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone_number' => $this->faker->phoneNumber(),
            'password' => static::$password ??= Hash::make('password'),
            'is_verified' => true,
            'profile_photo_path' => null,
            'remember_token' => Str::random(10),
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
