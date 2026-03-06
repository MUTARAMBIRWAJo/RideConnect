<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ManagerSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $managers = [
            [
                'name' => 'Admin Super',
                'email' => 'superadmin@rideconnect.rw',
                'password' => Hash::make('SuperAdmin@123'),
                'role' => 'SUPER_ADMIN',
                'created_at' => now()->subDays(90),
                'updated_at' => now()->subDays(90),
            ],
            [
                'name' => 'John Kamanzi',
                'email' => 'john.kamanzi@rideconnect.rw',
                'password' => Hash::make('Admin@123'),
                'role' => 'ADMIN',
                'created_at' => now()->subDays(80),
                'updated_at' => now()->subDays(80),
            ],
            [
                'name' => 'Sarah Uwase',
                'email' => 'sarah.uwase@rideconnect.rw',
                'password' => Hash::make('Officer@123'),
                'role' => 'OFFICER',
                'created_at' => now()->subDays(60),
                'updated_at' => now()->subDays(60),
            ],
            [
                'name' => 'Peter Ndayisaba',
                'email' => 'peter.ndayisaba@rideconnect.rw',
                'password' => Hash::make('Officer@123'),
                'role' => 'OFFICER',
                'created_at' => now()->subDays(45),
                'updated_at' => now()->subDays(45),
            ],
            [
                'name' => 'Yvonne Mutoni',
                'email' => 'yvonne.mutoni@rideconnect.rw',
                'password' => Hash::make('Accountant@123'),
                'role' => 'ACCOUNTANT',
                'created_at' => now()->subDays(50),
                'updated_at' => now()->subDays(50),
            ],
        ];

        foreach ($managers as $manager) {
            DB::table('managers')->insert($manager);
        }
    }
}
