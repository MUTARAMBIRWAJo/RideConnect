<?php

namespace Database\Seeders;

use App\Models\Driver;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DriverSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get mobile users with DRIVER role and sync their IDs
        $driverMobileUsers = DB::table('mobile_users')
            ->where('role', 'DRIVER')
            ->orderBy('id')
            ->limit(3)
            ->get();

        $licenseNumbers = ['DL-2024-001', 'DL-2024-002', 'DL-2024-003'];
        $licensePlates = ['RAC-123-A', 'RAC-456-B', 'RAC-789-C'];
        $totalRides = [45, 72, 28];
        $ratings = [4.80, 4.92, 4.65];
        $ratingCounts = [38, 65, 22];
        $balances = [250.00, 480.50, 150.00];
        $daysAgo = [30, 60, 15];

        $index = 0;
        foreach ($driverMobileUsers as $mobileUser) {
            // Get the corresponding user from users table
            $user = DB::table('users')
                ->where('mobile_user_id', $mobileUser->id)
                ->first();

            if ($user) {
                Driver::create([
                    'user_id' => $user->id,
                    'license_number' => $licenseNumbers[$index] ?? 'DL-' . date('Y') . '-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                    'license_plate' => $licensePlates[$index] ?? 'RAC-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT) . '-A',
                    'status' => 'approved',
                    'total_rides' => $totalRides[$index] ?? 0,
                    'rating' => $ratings[$index] ?? 0.00,
                    'rating_count' => $ratingCounts[$index] ?? 0,
                    'balance' => $balances[$index] ?? 0.00,
                    'approved_at' => now()->subDays($daysAgo[$index] ?? 0),
                ]);
                $index++;
            }
        }
    }
}
