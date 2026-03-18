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
        $trips = DB::table('trips')
            ->orderBy('id')
            ->limit(12)
            ->get(['id', 'passenger_id', 'fare', 'status', 'created_at', 'updated_at']);

        foreach ($trips as $trip) {
            $normalizedStatus = strtoupper((string) $trip->status);

            $paymentStatus = match ($normalizedStatus) {
                'COMPLETED' => 'PAID',
                'CANCELLED' => 'REFUNDED',
                default => 'PENDING',
            };

            $paymentMethod = match ((int) $trip->id % 3) {
                0 => 'card',
                1 => 'mobile_money',
                default => 'cash',
            };

            DB::table('payments_v2')->updateOrInsert(
                ['trip_id' => $trip->id],
                [
                    'passenger_id' => $trip->passenger_id,
                    'amount' => (float) $trip->fare,
                    'payment_method' => $paymentMethod,
                    'status' => $paymentStatus,
                    'transaction_reference' => $paymentStatus === 'PENDING'
                        ? null
                        : 'TXN-' . Str::upper(Str::random(12)),
                    'paid_at' => $paymentStatus === 'PENDING' ? null : now()->subDays((int) $trip->id % 21),
                    'created_at' => $trip->created_at ?? now(),
                    'updated_at' => $trip->updated_at ?? now(),
                ]
            );
        }
    }
}
