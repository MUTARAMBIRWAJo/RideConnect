<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Driver>
 */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function configure(): static
    {
        return $this->afterCreating(function (Driver $driver): void {
            $mobileUser = MobileUser::query()->find($driver->id);

            if (! $mobileUser) {
                $mobileUser = new MobileUser;
                $mobileUser->forceFill([
                    'id' => $driver->id,
                    'first_name' => 'Driver',
                    'last_name' => (string) $driver->id,
                    'email' => $driver->user?->email ?? 'driver'.$driver->id.'@example.test',
                    'phone' => $driver->user?->phone ?? '+2507'.str_pad((string) $driver->id, 8, '0', STR_PAD_LEFT),
                    'password' => bcrypt('password'),
                    'role' => 'DRIVER',
                    'is_verified' => true,
                ])->save();
            }

            if ($driver->user && ! $driver->user->mobile_user_id) {
                $driver->user->forceFill(['mobile_user_id' => $mobileUser->id])->save();
            }
        });
    }

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'license_number' => $this->faker->unique()->numerify('DL-##########'),
            'license_plate' => $this->faker->unique()->bothify('RW-###-??'),
            'status' => 'approved',
            'total_rides' => $this->faker->numberBetween(0, 100),
            'rating' => $this->faker->randomFloat(2, 3.5, 5.0),
            'rating_count' => $this->faker->numberBetween(0, 50),
            'balance' => $this->faker->randomFloat(2, 0, 50000),
            'approved_at' => now()->subDays($this->faker->numberBetween(1, 30)),
        ];
    }

    /**
     * Indicate that the driver is offline.
     */
    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'availability_status' => 'offline',
        ]);
    }

    /**
     * Indicate that the driver is pending approval.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'approved_at' => null,
        ]);
    }
}
