<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only create table if it doesn't already exist
        if (!Schema::hasTable('saved_locations')) {
            Schema::create('saved_locations', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255)->unique();
                $table->decimal('lat', 10, 8); // Latitude: -90 to 90
                $table->decimal('lng', 11, 8); // Longitude: -180 to 180
                $table->timestamps();
                
                // Index for LIKE queries
                $table->index('name');
            });

            // Seed initial Rwanda locations for fallback geocoding
            $this->seedRwandaLocations();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_locations');
    }

    /**
     * Seed common Rwanda locations.
     */
    private function seedRwandaLocations(): void
    {
        DB::table('saved_locations')->insert([
            // Kigali Central
            [
                'name' => 'Kimironko Market',
                'lat' => -1.9480,
                'lng' => 30.0619,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Nyabugogo Bus Park',
                'lat' => -1.9487,
                'lng' => 30.0597,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Remera Bus Park',
                'lat' => -1.9567,
                'lng' => 30.1056,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Downtown Bus Park',
                'lat' => -1.9554,
                'lng' => 29.8747,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Additional key terminals
            [
                'name' => 'Kigali Airport',
                'lat' => -1.9717,
                'lng' => 30.1388,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Kigali Central Station',
                'lat' => -1.9511,
                'lng' => 30.0574,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Gisozi Bus Station',
                'lat' => -1.9422,
                'lng' => 30.0689,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Muhima Market',
                'lat' => -1.9634,
                'lng' => 29.9697,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Circular Bus Station',
                'lat' => -1.9531,
                'lng' => 30.0551,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Chez Lando Market',
                'lat' => -1.9489,
                'lng' => 30.0548,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
};
