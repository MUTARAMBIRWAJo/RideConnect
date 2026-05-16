<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CreateTestUsers extends Seeder
{
    public function run(): void
    {
        // Ensure roles exist
        $roles = ['Super_admin', 'Admin', 'Accountant', 'Officer'];
        $roleIds = [];
        foreach ($roles as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            $roleIds[$roleName] = $role->id;
        }

        echo "✓ Roles ensured\n";

        // Create users directly via raw inserts
        $users = [
            [
                'email' => 'superadmin@rideconnect.rw',
                'name' => 'Admin Super',
                'password' => Hash::make('SuperAdmin@123'),
                'role' => 'SUPER_ADMIN',
                'role_name' => 'Super_admin',
            ],
            [
                'email' => 'john.kamanzi@rideconnect.rw',
                'name' => 'John Kamanzi',
                'password' => Hash::make('Admin@123'),
                'role' => 'ADMIN',
                'role_name' => 'Admin',
            ],
            [
                'email' => 'yvonne.mutoni@rideconnect.rw',
                'name' => 'Yvonne Mutoni',
                'password' => Hash::make('Accountant@123'),
                'role' => 'ACCOUNTANT',
                'role_name' => 'Accountant',
            ],
            [
                'email' => 'sarah.uwase@rideconnect.rw',
                'name' => 'Sarah Uwase',
                'password' => Hash::make('Officer@123'),
                'role' => 'OFFICER',
                'role_name' => 'Officer',
            ],
        ];

        foreach ($users as $userData) {
            $roleName = $userData['role_name'];
            $email = $userData['email'];

            // Insert or update user
            $userId = DB::table('users')->where('email', $email)->value('id');
            
            if (!$userId) {
                $userId = DB::table('users')->insertGetId([
                    'email' => $email,
                    'name' => $userData['name'],
                    'password' => $userData['password'],
                    'role' => $userData['role'],
                    'is_approved' => true,
                    'is_verified' => true,
                    'approved_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                echo "✓ Created user: {$email}\n";
            } else {
                DB::table('users')->where('id', $userId)->update([
                    'name' => $userData['name'],
                    'password' => $userData['password'],
                    'role' => $userData['role'],
                    'is_approved' => true,
                    'is_verified' => true,
                    'approved_at' => now(),
                    'updated_at' => now(),
                ]);
                echo "✓ Updated user: {$email}\n";
            }

            // Assign role (delete old, insert new)
            DB::table('model_has_roles')->where(['model_id' => $userId, 'model_type' => 'App\\Models\\User'])->delete();
            DB::table('model_has_roles')->insert([
                'role_id' => $roleIds[$roleName],
                'model_id' => $userId,
                'model_type' => 'App\\Models\\User',
            ]);
            echo "  → Synced role: {$roleName}\n";
        }

        echo "\n✨ All test users created with proper roles!\n";
    }
}
