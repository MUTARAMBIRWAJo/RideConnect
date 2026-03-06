<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MobileUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mobileUsers = [
            // Drivers
            [
                'first_name' => 'Jean',
                'last_name' => 'Mugabo',
                'phone' => '+250788100001',
                'email' => 'jean.mugabo@example.com',
                'password' => Hash::make('password123'),
                'role' => 'DRIVER',
                'profile_photo' => 'profiles/jean_mugabo.jpg',
                'is_verified' => true,
                'created_at' => now()->subDays(60),
                'updated_at' => now()->subDays(60),
            ],
            [
                'first_name' => 'Patrick',
                'last_name' => 'Habimana',
                'phone' => '+250788100002',
                'email' => 'patrick.habimana@example.com',
                'password' => Hash::make('password123'),
                'role' => 'DRIVER',
                'profile_photo' => 'profiles/patrick_habimana.jpg',
                'is_verified' => true,
                'created_at' => now()->subDays(45),
                'updated_at' => now()->subDays(45),
            ],
            [
                'first_name' => 'Claude',
                'last_name' => 'Niyonzima',
                'phone' => '+250788100003',
                'email' => 'claude.niyonzima@example.com',
                'password' => Hash::make('password123'),
                'role' => 'DRIVER',
                'profile_photo' => null,
                'is_verified' => true,
                'created_at' => now()->subDays(30),
                'updated_at' => now()->subDays(30),
            ],
            [
                'first_name' => 'Eric',
                'last_name' => 'Nsanzimana',
                'phone' => '+250788100004',
                'email' => 'eric.nsanzimana@example.com',
                'password' => Hash::make('password123'),
                'role' => 'DRIVER',
                'profile_photo' => null,
                'is_verified' => false,
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ],
            // Passengers
            [
                'first_name' => 'Alice',
                'last_name' => 'Uwimana',
                'phone' => '+250788200001',
                'email' => 'alice.uwimana@example.com',
                'password' => Hash::make('password123'),
                'role' => 'PASSENGER',
                'profile_photo' => 'profiles/alice_uwimana.jpg',
                'is_verified' => true,
                'created_at' => now()->subDays(50),
                'updated_at' => now()->subDays(50),
            ],
            [
                'first_name' => 'Grace',
                'last_name' => 'Mukamana',
                'phone' => '+250788200002',
                'email' => 'grace.mukamana@example.com',
                'password' => Hash::make('password123'),
                'role' => 'PASSENGER',
                'profile_photo' => null,
                'is_verified' => true,
                'created_at' => now()->subDays(40),
                'updated_at' => now()->subDays(40),
            ],
            [
                'first_name' => 'David',
                'last_name' => 'Tuyishime',
                'phone' => '+250788200003',
                'email' => 'david.tuyishime@example.com',
                'password' => Hash::make('password123'),
                'role' => 'PASSENGER',
                'profile_photo' => 'profiles/david_tuyishime.jpg',
                'is_verified' => true,
                'created_at' => now()->subDays(25),
                'updated_at' => now()->subDays(25),
            ],
            [
                'first_name' => 'Marie',
                'last_name' => 'Ingabire',
                'phone' => '+250788200004',
                'email' => 'marie.ingabire@example.com',
                'password' => Hash::make('password123'),
                'role' => 'PASSENGER',
                'profile_photo' => null,
                'is_verified' => true,
                'created_at' => now()->subDays(15),
                'updated_at' => now()->subDays(15),
            ],
            [
                'first_name' => 'Samuel',
                'last_name' => 'Bizimungu',
                'phone' => '+250788200005',
                'email' => 'samuel.bizimungu@example.com',
                'password' => Hash::make('password123'),
                'role' => 'PASSENGER',
                'profile_photo' => null,
                'is_verified' => false,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ],
            [
                'first_name' => 'Diane',
                'last_name' => 'Muhire',
                'phone' => '+250788200006',
                'email' => 'diane.muhire@example.com',
                'password' => Hash::make('password123'),
                'role' => 'PASSENGER',
                'profile_photo' => null,
                'is_verified' => true,
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ],
        ];

        foreach ($mobileUsers as $user) {
            DB::table('mobile_users')->insert($user);
        }
    }
}
