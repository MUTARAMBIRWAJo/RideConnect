<?php

namespace Database\Seeders;

use App\Models\Corridor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransportCorridorSeeder extends Seeder
{
    private const COORDS = [
        'remera bus park'              => [-1.9400, 30.1200],
        'rembera bus park'             => [-1.9400, 30.1200],
        'down town bus park'           => [-1.9536, 30.0600],
        'downtown bus park'            => [-1.9536, 30.0600],
        'rubirizi bus terminal'        => [-1.9630, 30.0800],
        'rubilizi bus terminal'        => [-1.9630, 30.0800],
        'nyanza bus park'              => [-1.9800, 30.1400],
        'bwerankori bus terminal'      => [-2.0200, 30.0600],
        'nyabugogo bus park'           => [-1.9750, 30.0400],
        'sez bus terminal'             => [-1.9500, 30.2000],
        'kabuga bus park'              => [-1.9200, 30.1000],
        'busanza bus terminal'         => [-2.0100, 30.2200],
        'kibaya bus terminal'          => [-1.9600, 30.0600],
        'ndera bus terminal'           => [-1.9300, 30.1900],
        'masaka bus terminal'          => [-2.0000, 30.2500],
        'masoro (auca) bus terminal'   => [-1.9600, 30.1500],
        'gasogi bus terminal'          => [-1.9700, 30.1100],
        'kinya'                        => [-1.9400, 30.1400],
        'kimironko'                    => [-1.9500, 30.1100],
        'kacyiru'                      => [-1.9400, 30.0700],
        'kigali'                       => [-1.9536, 30.0600],
        'city centre'                  => [-1.9536, 30.0600],
    ];

    private const DAYS = [1, 2, 3, 4, 5, 6];
    private const SLOTS = [
        ['06:00:00', '06:30:00'], ['07:00:00', '07:30:00'],
        ['08:00:00', '08:30:00'], ['12:00:00', '12:30:00'],
        ['14:00:00', '14:30:00'], ['16:00:00', '16:30:00'],
        ['17:00:00', '17:30:00'], ['18:00:00', '18:30:00'],
    ];

    public function run(): void
    {
        $legacy = Corridor::query()->orderBy('id')->get();

        $allStopRows   = [];
        $allTimeRows   = [];
        $seeded = 0;
        $skipped = 0;

        foreach ($legacy as $c) {
            $name = trim((string) $c->name);

            if (DB::table('transport_corridors')->where('corridor_name', $name)->exists()) {
                $skipped++;
                continue;
            }

            [$code, $origin, $destination, $duration] = $this->parseName($name, $c->id);

            if ($code === null) {
                $skipped++;
                continue;
            }

            $tcId = DB::table('transport_corridors')->insertGetId([
                'corridor_code'              => $code,
                'corridor_name'              => $name,
                'transport_type'             => 'BUS',
                'status'                     => 'active',
                'estimated_duration_minutes' => $duration,
                'created_at'                 => now(),
                'updated_at'                 => now(),
            ]);

            [$oLat, $oLng] = $this->lookupCoords($origin);
            [$dLat, $dLng] = $this->lookupCoords($destination);

            $stopStart = $stopEnd = null;

            $stopStart = DB::table('corridor_stops')->insertGetId([
                'corridor_id'    => $tcId,
                'stop_name'      => $origin,
                'stop_order'     => 1,
                'latitude'       => $oLat,
                'longitude'      => $oLng,
                'is_major_terminal' => true,
                'status'         => 'active',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            $stopEnd = DB::table('corridor_stops')->insertGetId([
                'corridor_id'    => $tcId,
                'stop_name'      => $destination,
                'stop_order'     => 2,
                'latitude'       => $dLat,
                'longitude'      => $dLng,
                'is_major_terminal' => true,
                'status'         => 'active',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            foreach (self::DAYS as $day) {
                foreach (self::SLOTS as [$arr, $dep]) {
                    $allTimeRows[] = [
                        'corridor_id'         => $tcId,
                        'corridor_stop_id'    => $stopStart,
                        'scheduled_arrival_time'    => $arr,
                        'scheduled_departure_time'  => $dep,
                        'service_day_of_week' => $day,
                        'status'              => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $allTimeRows[] = [
                        'corridor_id'         => $tcId,
                        'corridor_stop_id'    => $stopEnd,
                        'scheduled_arrival_time'    => $arr,
                        'scheduled_departure_time'  => $dep,
                        'service_day_of_week' => $day,
                        'status'              => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            $seeded++;
        }

        if (!empty($allTimeRows)) {
            // Chunk to avoid hitting PostgreSQL max parameter count
            foreach (array_chunk($allTimeRows, 500) as $chunk) {
                DB::table('corridor_stop_times')->insert($chunk);
            }
        }

        $this->command?->info("TransportCorridorSeeder: seeded {$seeded}, skipped {$skipped}.");
    }

    private function parseName(string $name, int $legacyId): ?array
    {
        if (preg_match('/^Corridor\s+([A-Z])$/i', $name, $m)) {
            $letter = strtoupper($m[1]);
            return ["CORR-{$letter}", "Corridor {$letter} Origin", "Corridor {$letter} Destination", 30];
        }

        if (preg_match('/^(.*?)\s*->\s*(.*?)\s*\((\w+)\)$/i', $name, $m)) {
            $origin      = trim($m[1]);
            $destination = trim($m[2]);
            $code        = strtoupper(trim($m[3]));
            $duration    = $this->estimateDuration($destination);
            return [$code, $origin, $destination, $duration];
        }

        if (str_contains($name, '-')) {
            [$origin, $destination] = array_map('trim', explode('-', $name, 2));
            $slug = strtolower(str_replace(' ', '', $origin . '-' . $destination));
            return [strtoupper(Str::substr(Str::slug($slug), 0, 12)), $origin, $destination, 25];
        }

        if ($name !== '') {
            return ["CORR-" . strtoupper(Str::substr(Str::slug($name), 0, 8)), $name, $name, 20];
        }

        return null;
    }

    private function estimateDuration(string $destination): int
    {
        $map = [
            'kigali'     => 90, 'busanza' => 45, 'nder a'  => 60,
            'masaka'     => 90, 'masoro'  => 120, 'gasogi'  => 75,
            'kabuga'     => 30, 'nyabugogo' => 20, 'kibaya' => 20,
            'kimironko'  => 20, 'down town'  => 15, 'downtown' => 15,
            'bwerankori' => 60, 'sez'      => 30, 'nyanza'  => 40,
            'remera'     => 10, 'kacyiru' => 15,
        ];
        $n = strtolower($destination);
        foreach ($map as $key => $min) {
            if (str_contains($n, $key)) return $min;
        }
        if (preg_match('/^corridor\s+[a-z]$/', $n)) return 30;
        return 30;
    }

    private function lookupCoords(string $stop): array
    {
        $key = strtolower($stop);
        foreach (self::COORDS as $known => [$lat, $lng]) {
            if (str_contains($key, $known)) return [$lat, $lng];
        }
        return [null, null];
    }
}
