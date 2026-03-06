<?php

namespace Database\Seeders;

use App\Models\Payment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $payments = [
            [
                'booking_id' => 1,
                'user_id' => 5,
                'amount' => 50.00,
                'platform_fee' => 5.00,
                'driver_amount' => 45.00,
                'currency' => 'RWF',
                'payment_method' => 'mobile_money',
                'transaction_id' => 'TXN-' . uniqid(),
                'status' => 'completed',
                'payment_details' => json_encode(['provider' => 'MTN Mobile Money', 'phone' => '+250788000001']),
                'paid_at' => now()->subHours(6),
            ],
            [
                'booking_id' => 2,
                'user_id' => 6,
                'amount' => 35.00,
                'platform_fee' => 3.50,
                'driver_amount' => 31.50,
                'currency' => 'RWF',
                'payment_method' => 'card',
                'transaction_id' => 'TXN-' . uniqid(),
                'status' => 'pending',
                'payment_details' => json_encode(['card_last4' => '4242', 'brand' => 'Visa']),
            ],
            [
                'booking_id' => 3,
                'user_id' => 7,
                'amount' => 20.00,
                'platform_fee' => 2.00,
                'driver_amount' => 18.00,
                'currency' => 'RWF',
                'payment_method' => 'mobile_money',
                'transaction_id' => 'TXN-' . uniqid(),
                'status' => 'completed',
                'payment_details' => json_encode(['provider' => 'Airtel Money', 'phone' => '+250782000001']),
                'paid_at' => now()->subHours(2),
            ],
        ];

        foreach ($payments as $payment) {
            Payment::create($payment);
        }
    }
}
