<?php

namespace Database\Seeders\Matching;

use App\Models\Driver;
use App\Models\DriverAvailabilitySnapshot;
use App\Models\DriverLocation;
use App\Models\Vehicle;
use App\Services\Matching\DriverEligibilityAuditor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PremiumKigaliDriverSeeder extends Seeder
{
    private const PASSWORD = 'password123';

    private array $vehicles = [
        ['make' => 'Toyota', 'model' => 'Corolla', 'vehicle_type' => 'sedan', 'seats' => 4],
        ['make' => 'Toyota', 'model' => 'Prius', 'vehicle_type' => 'compact', 'seats' => 4],
        ['make' => 'Toyota', 'model' => 'RAV4', 'vehicle_type' => 'suv', 'seats' => 5],
        ['make' => 'Hyundai', 'model' => 'Tucson', 'vehicle_type' => 'suv', 'seats' => 5],
        ['make' => 'Nissan', 'model' => 'X-Trail', 'vehicle_type' => 'suv', 'seats' => 5],
        ['make' => 'Kia', 'model' => 'Sportage', 'vehicle_type' => 'suv', 'seats' => 5],
    ];

    private array $names = [
        'Jean Bosco', 'Aline Uwase', 'Eric Niyonsenga', 'Diane Mukamana', 'Patrick Habimana',
        'Grace Ingabire', 'Claude Mugisha', 'Divine Iradukunda', 'Emmanuel Nshimiyimana', 'Sandrine Uwera',
        'Olivier Tuyisenge', 'Chantal Nyirahabimana', 'Fabrice Nsengiyumva', 'Clarisse Umutesi', 'Thierry Bizimana',
        'Josiane Mukamana', 'Aimable Nkurunziza', 'Yvonne Ishimwe', 'Pacifique Hakizimana', 'Alice Mutoni',
        'Samuel Rukundo', 'Beata Mukeshimana', 'David Ndayisenga', 'Liliane Uwimana', 'Felicien Manirakiza',
        'Jeannette Mukamana', 'Theogene Nsabimana', 'Vestine Uwamahoro', 'Innocent Niyonzima', 'Odette Nyiraneza',
        'Arsene Mugenzi', 'Rosine Umuhoza', 'Elie Nshimiyimana', 'Solange Mukamana', 'Blaise Habumugisha',
        'Esperance Mukandayisenga', 'Cedric Tuyishime', 'Noella Ingabire', 'Gilbert Nkurikiyimana', 'Francine Uwera',
        'Herve Niyitegeka', 'Ange Umutoni', 'Moise Habyarimana', 'Claudine Mukarugwiza', 'Prosper Twagirayezu',
        'Marina Imanizabayo', 'Didier Ndayambaje', 'Juliette Mukasine', 'Rene Murenzi', 'Belise Kaneza',
    ];

    public function run(): void
    {
        $drivers = $this->expandedDrivers();
        $now = now();

        foreach ($drivers as $index => $data) {
            [$firstName, $lastName] = explode(' ', $data['name'], 2) + ['', 'Driver'];
            $phone = $this->phone($index);
            $email = 'premium.driver.'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'@rideconnect.local';
            $plate = $this->plate($index);
            $vehicle = $this->vehicles[$index % count($this->vehicles)];

            $mobileUser = $this->upsertMobileUser($firstName, $lastName, $phone, $email);

            $user = \App\Models\User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $data['name'],
                    'phone' => $phone,
                    'password' => bcrypt(self::PASSWORD),
                    'role' => 'DRIVER',
                    'mobile_user_id' => $mobileUser->id,
                    'is_approved' => true,
                    'is_verified' => true,
                    'approved_at' => $now,
                    'email_verified_at' => $now,
                    'last_seen_at' => $now,
                    'is_online' => true,
                    'current_device_id' => 'seed-driver-'.$index,
                ]
            );

            $driver = Driver::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'license_number' => 'RDL-'.str_pad((string) (700000 + $index), 6, '0', STR_PAD_LEFT),
                    'license_plate' => $plate,
                    'status' => 'approved',
                    'availability_status' => 'available',
                    'is_available' => true,
                    'current_trip_id' => null,
                    'is_test' => false,
                    'current_latitude' => $data['lat'],
                    'current_longitude' => $data['lng'],
                    'last_location_lat' => $data['lat'],
                    'last_location_lng' => $data['lng'],
                    'last_online_at' => $now,
                    'online_since' => $now->copy()->subMinutes(20),
                    'last_seen_at' => $now,
                    'is_online' => true,
                    'total_rides' => 120 + $index,
                    'rating' => 4.75 + (($index % 5) / 100),
                    'rating_count' => 45 + $index,
                    'balance' => 0,
                    'approved_at' => $now->copy()->subDays(30),
                ]
            );

            if (Schema::hasColumn('drivers', 'is_active')) {
                $driver->forceFill(['is_active' => true])->save();
            }

            $vehiclePayload = [
                    'make' => $vehicle['make'],
                    'model' => $vehicle['model'],
                    'year' => 2020 + ($index % 5),
                    'color' => ['White', 'Silver', 'Black', 'Blue', 'Grey'][$index % 5],
                    'vehicle_type' => $vehicle['vehicle_type'],
                    'seats' => $vehicle['seats'],
                    'air_conditioning' => true,
                    'is_active' => true,
                    'verified_at' => $now->copy()->subDays(10),
            ];

            if (Schema::hasColumn('vehicles', 'maintenance_status')) {
                $vehiclePayload['maintenance_status'] = 'good';
            }

            Vehicle::query()->updateOrCreate(
                ['driver_id' => $driver->id],
                $vehiclePayload
            );

            DriverLocation::query()->updateOrCreate(
                ['driver_id' => $mobileUser->id],
                [
                    'latitude' => $data['lat'],
                    'longitude' => $data['lng'],
                    'lat' => $data['lat'],
                    'lng' => $data['lng'],
                    'speed' => 0,
                    'speed_kmh' => 0,
                    'heading' => ($index * 17) % 360,
                    'accuracy' => 6.0 + ($index % 4),
                    'recorded_at' => $now,
                    'last_activity_at' => $now,
                    'updated_at' => $now,
                    'is_online' => true,
                    'trip_id' => null,
                ]
            );

            DriverAvailabilitySnapshot::query()->create([
                'driver_id' => $driver->id,
                'availability_status' => 'available',
                'latitude' => $data['lat'],
                'longitude' => $data['lng'],
                'metadata' => [
                    'seeded_by' => static::class,
                    'zone' => $data['zone'],
                    'heartbeat' => 'active',
                    'vehicle_verified' => true,
                ],
            ]);

            if (Schema::hasTable('driver_availability_cache')) {
                DB::table('driver_availability_cache')->updateOrInsert(
                    ['driver_id' => $driver->id],
                    [
                        'vehicle_type' => $vehicle['vehicle_type'],
                        'current_lat' => $data['lat'],
                        'current_lng' => $data['lng'],
                        'availability_score' => 0.98,
                        'is_online' => true,
                        'is_available' => true,
                        'last_seen_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        $this->command?->info('Seeded 50 premium Kigali private-car drivers with vehicles, live locations, snapshots, and availability cache.');
    }

    private function expandedDrivers(): array
    {
        $rows = [];
        foreach (DriverEligibilityAuditor::DRIVER_DISTRIBUTION as $zone) {
            for ($i = 0; $i < $zone['count']; $i++) {
                $index = count($rows);
                $rows[] = [
                    'name' => $this->names[$index],
                    'zone' => $zone['zone'],
                    'lat' => round($zone['lat'] + (($i - 1) * 0.0018) + (($index % 2) * 0.0007), 7),
                    'lng' => round($zone['lng'] + (($i % 3 - 1) * 0.0019) - (($index % 2) * 0.0006), 7),
                ];
            }
        }

        return array_slice($rows, 0, 50);
    }

    private function upsertMobileUser(string $firstName, string $lastName, string $phone, string $email): object
    {
        $existing = DB::table('mobile_users')->where('email', $email)->first();
        $payload = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'email' => $email,
            'password' => bcrypt(self::PASSWORD),
            'role' => 'DRIVER',
            'is_verified' => true,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('mobile_users')->where('id', $existing->id)->update($payload);
            return DB::table('mobile_users')->where('id', $existing->id)->first();
        }

        $payload['created_at'] = now();
        $id = DB::table('mobile_users')->insertGetId($payload);

        return DB::table('mobile_users')->where('id', $id)->first();
    }

    private function phone(int $index): string
    {
        return '0788'.str_pad((string) (220000 + $index), 6, '0', STR_PAD_LEFT);
    }

    private function plate(int $index): string
    {
        $letters = range('A', 'Z');
        return 'R'.($letters[(int) floor($index / 26)] ?? 'A').$letters[$index % 26].' '.str_pad((string) (100 + $index), 3, '0', STR_PAD_LEFT).' '.chr(65 + ($index % 26));
    }
}
