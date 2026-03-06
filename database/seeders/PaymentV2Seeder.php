<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentV2Seeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $payments = [
            // Payment for Trip 1 - Completed
            [
                'trip_id' => 1,
                'passenger_id' => 5, // Alice Uwimana
                'amount' => 5500.00,
                'payment_method' => 'mobile_money',
                'status' => 'PAID',
                'transaction_reference' => 'TXN-' . Str::upper(Str::random(12)),
                'paid_at' => now()->subDays(20)->setHour(8)->setMinute(46),
                'created_at' => now()->subDays(20),
                'updated_at' => now()->subDays(20),
            ],
            // Payment for Trip 2 - Completed
            [
                'trip_id' => 2,
                'passenger_id' => 6, // Grace Mukamana
                'amount' => 3200.00,
                'payment_method' => 'mobile_money',
                'status' => 'PAID',
                'transaction_reference' => 'TXN-' . Str::upper(Str::random(12)),
                'paid_at' => now()->subDays(18)->setHour(14)->setMinute(36),
                'created_at' => now()->subDays(18),
                'updated_at' => now()->subDays(18),
            ],
            // Payment for Trip 3 - Completed
            [
                'trip_id' => 3,
                'passenger_id' => 7, // David Tuyishime
                'amount' => 2000.00,
                'payment_method' => 'cash',
                'status' => 'PAID',
                'transaction_reference' => 'CASH-' . Str::upper(Str::random(12)),
                'paid_at' => now()->subDays(15)->setHour(10)->setMinute(21),
                'created_at' => now()->subDays(15),
                'updated_at' => now()->subDays(15),
            ],
            // Payment for Trip 4 - Completed
            [
                'trip_id' => 4,
                'passenger_id' => 8, // Marie Ingabire
                'amount' => 1800.00,
                'payment_method' => 'card',
                'status' => 'PAID',
                'transaction_reference' => 'TXN-' . Str::upper(Str::random(12)),
                'paid_at' => now()->subDays(10)->setHour(18)->setMinute(6),
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ],
            // Payment for Trip 5 - Completed
            [
                'trip_id' => 5,
                'passenger_id' => 10, // Diane Muhire
                'amount' => 4000.00,
                'payment_method' => 'mobile_money',
                'status' => 'PAID',
                'transaction_reference' => 'TXN-' . Str::upper(Str::random(12)),
                'paid_at' => now()->subDays(7)->setHour(9)->setMinute(46),
                'created_at' => now()->subDays(7),
                'updated_at' => now()->subDays(7),
            ],
            // Payment for Trip 6 - In progress (Pending)
            [
                'trip_id' => 6,
                'passenger_id' => 5, // Alice Uwimana
                'amount' => 2500.00,
                'payment_method' => 'mobile_money',
                'status' => 'PENDING',
                'transaction_reference' => null,
                'paid_at' => null,
                'created_at' => now()->subHours(1),
                'updated_at' => now()->subHours(1),
            ],
            // Payment for Trip 9 - Cancelled trip (Refunded)
            [
                'trip_id' => 9,
                'passenger_id' => 8, // Marie Ingabire
                'amount' => 1500.00,
                'payment_method' => 'mobile_money',
                'status' => 'REFUNDED',
                'transaction_reference' => 'TXN-' . Str::upper(Str::random(12)),
                'paid_at' => now()->subDays(5)->setHour(11)->setMinute(5),
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(4),
            ],
            // Payment for Trip 10 - Completed
            [
                'trip_id' => 10,
                'passenger_id' => 10, // Diane Muhire
                'amount' => 6000.00,
                'payment_method' => 'card',
                'status' => 'PAID',
                'transaction_reference' => 'TXN-' . Str::upper(Str::random(12)),
                'paid_at' => now()->subDays(3)->setHour(6)->setMinute(51),
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ],
        ];

        foreach ($payments as $payment) {
            DB::table('payments_v2')->insert($payment);
        }
    }
}
