<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SavedLocationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if table exists
        if (!DB::getSchemaBuilder()->hasTable('saved_locations')) {
            $this->command->error('saved_locations table does not exist. Please run the migration first.');
            return;
        }

        $locations = [
            [
                'name' => 'Kigali, Rwanda',
                'lat' => -1.9536,
                'lng' => 30.0605,
            ],
            [
                'name' => 'Kigali International Airport',
                'lat' => -1.9717,
                'lng' => 30.1388,
            ],
        ];

        foreach ($locations as $location) {
            $exists = DB::table('saved_locations')
                ->where('name', $location['name'])
                ->exists();

            if (!$exists) {
                DB::table('saved_locations')->insert([
                    'name' => $location['name'],
                    'lat' => $location['lat'],
                    'lng' => $location['lng'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->command->info("Added location: {$location['name']}");
            } else {
                $this->command->info("Location already exists: {$location['name']}");
            }
        }

        $this->command->info('Saved locations seeding completed.');
    }
}
