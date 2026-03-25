<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'user_id' => User::factory(),
            'amount' => $this->faker->randomFloat(2, 1000, 100000),
            'driver_amount' => $this->faker->randomFloat(2, 1000, 100000),
            'platform_fee' => $this->faker->randomFloat(2, 0, 10000),
            'currency' => 'RWF',
            'payment_method' => 'card',
            'payment_provider' => 'stripe',
            'provider_transaction_id' => $this->faker->uuid(),
            'webhook_event_id' => $this->faker->uuid(),
            'verification_status' => 'verified',
            'transaction_id' => $this->faker->uuid(),
            'supabase_payment_id' => null,
            'status' => 'COMPLETED',
            'payment_details' => null,
            'paid_at' => now(),
            'refunded_at' => null,
        ];
    }
}
