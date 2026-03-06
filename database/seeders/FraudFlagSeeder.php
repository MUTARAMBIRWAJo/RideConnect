<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\FraudFlag;
use App\Models\MobileUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FraudFlagSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $drivers    = Driver::take(3)->get();
        $passengers = MobileUser::take(3)->get();

        if ($drivers->isEmpty()) {
            $this->command->warn('FraudFlagSeeder: no drivers found, skipping.');
            return;
        }

        // Resolver — first SUPER_ADMIN user
        $superAdminId = User::where('role', 'SUPER_ADMIN')->value('id');

        $now   = Carbon::now();
        $count = 0;

        // ------------------------------------------------------------------
        // 1. High-severity: duplicate ride detection for Driver 1
        // ------------------------------------------------------------------
        if ($drivers->first()) {
            FraudFlag::create([
                'entity_type' => 'driver',
                'entity_id'   => $drivers[0]->id,
                'reason'      => 'Duplicate ride detected: same pickup/drop-off within 25 minutes.',
                'severity'    => 'high',
                'resolved'    => false,
                'metadata'    => [
                    'detected_at'    => $now->subHours(3)->toIso8601String(),
                    'fare_amount_rwf' => 12000,
                    'ride_count'     => 2,
                    'window_minutes' => 25,
                ],
            ]);
            $count++;
        }

        // ------------------------------------------------------------------
        // 2. High-severity: abnormal fare spike for Driver 1
        // ------------------------------------------------------------------
        if ($drivers->first()) {
            FraudFlag::create([
                'entity_type' => 'driver',
                'entity_id'   => $drivers[0]->id,
                'reason'      => 'Fare amount 4.2× 30-day average (avg: 8,500 RWF, fare: 35,700 RWF).',
                'severity'    => 'high',
                'resolved'    => true,
                'resolved_by' => $superAdminId,
                'resolved_at' => $now->subDays(1)->setTime(14, 30),
                'metadata'    => [
                    'detected_at'     => $now->subDays(2)->toIso8601String(),
                    'fare_amount_rwf'  => 35700,
                    'avg_fare_rwf'    => 8500,
                    'multiplier'      => 4.2,
                    'resolution_note' => 'Verified: long-distance airport transfer. Cleared.',
                ],
            ]);
            $count++;
        }

        // ------------------------------------------------------------------
        // 3. Medium-severity: self-booking suspicion for Driver 2
        // ------------------------------------------------------------------
        if (isset($drivers[1]) && $passengers->isNotEmpty()) {
            FraudFlag::create([
                'entity_type' => 'driver',
                'entity_id'   => $drivers[1]->id,
                'reason'      => 'Possible self-booking: 4 rides with the same passenger in 7 days.',
                'severity'    => 'medium',
                'resolved'    => false,
                'metadata'    => [
                    'detected_at'   => $now->subHours(18)->toIso8601String(),
                    'passenger_id'  => $passengers->first()->id,
                    'booking_count' => 4,
                    'window_days'   => 7,
                ],
            ]);
            $count++;
        }

        // ------------------------------------------------------------------
        // 4. Medium-severity: midnight activity for Driver 2
        // ------------------------------------------------------------------
        if (isset($drivers[1])) {
            FraudFlag::create([
                'entity_type' => 'driver',
                'entity_id'   => $drivers[1]->id,
                'reason'      => 'Suspicious midnight activity: 3 completed rides between 01:00–03:00.',
                'severity'    => 'medium',
                'resolved'    => true,
                'resolved_by' => $superAdminId,
                'resolved_at' => $now->subDays(3)->setTime(9, 15),
                'metadata'    => [
                    'detected_at' => $now->subDays(4)->toIso8601String(),
                    'ride_count'  => 3,
                    'window'      => '01:00–03:00',
                    'total_fare_rwf' => 27000,
                ],
            ]);
            $count++;
        }

        // ------------------------------------------------------------------
        // 5. Low-severity: rate-limit warning for Driver 3
        // ------------------------------------------------------------------
        if (isset($drivers[2])) {
            FraudFlag::create([
                'entity_type' => 'driver',
                'entity_id'   => $drivers[2]->id,
                'reason'      => 'Payout rate limit approaching: 42/50 payouts processed this hour.',
                'severity'    => 'low',
                'resolved'    => false,
                'metadata'    => [
                    'detected_at'    => $now->subMinutes(45)->toIso8601String(),
                    'payout_count'   => 42,
                    'rate_limit'     => 50,
                    'window_minutes' => 60,
                ],
            ]);
            $count++;
        }

        // ------------------------------------------------------------------
        // 6. Medium-severity: flagged passenger account
        // ------------------------------------------------------------------
        if ($passengers->count() >= 2) {
            FraudFlag::create([
                'entity_type' => 'passenger',
                'entity_id'   => $passengers[1]->id,
                'reason'      => 'Multiple payment reversals in 14 days (3 chargebacks, total 45,000 RWF).',
                'severity'    => 'medium',
                'resolved'    => false,
                'metadata'    => [
                    'detected_at'      => $now->subDays(2)->toIso8601String(),
                    'chargeback_count' => 3,
                    'total_amount_rwf' => 45000,
                    'window_days'      => 14,
                ],
            ]);
            $count++;
        }

        // ------------------------------------------------------------------
        // 7. High-severity: transaction-level flag (webhook replay attempt)
        // ------------------------------------------------------------------
        FraudFlag::create([
            'entity_type' => 'transaction',
            'entity_id'   => 1001,   // synthetic payment reference
            'reason'      => 'Duplicate webhook event_id received for payment ref TXN-RW-1001 (possible replay attack).',
            'severity'    => 'high',
            'resolved'    => true,
            'resolved_by' => $superAdminId,
            'resolved_at' => $now->subDays(5)->setTime(11, 0),
            'metadata'    => [
                'detected_at'     => $now->subDays(5)->toIso8601String(),
                'event_id'        => 'evt_stripe_rw_test_duplicate_001',
                'amount_rwf'      => 18500,
                'resolution_note' => 'Confirmed replay. Payment not double-processed. Webhook blocked.',
            ],
        ]);
        $count++;

        $this->command->info("FraudFlagSeeder: {$count} fraud flags seeded (RWF).");
    }
}
