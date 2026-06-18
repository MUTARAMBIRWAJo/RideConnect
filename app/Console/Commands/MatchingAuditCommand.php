<?php

namespace App\Console\Commands;

use App\Models\Driver;
use App\Services\Matching\DriverEligibilityAuditor;
use Illuminate\Console\Command;

class MatchingAuditCommand extends Command
{
    protected $signature = 'matching:audit
        {--transport=private_car : private_car, motor_vehicle, or public_bus}
        {--lat= : Optional pickup latitude for radius checks}
        {--lng= : Optional pickup longitude for radius checks}
        {--radius=5 : Search radius in kilometers}
        {--legacy : Use legacy 15-minute heartbeat instead of V3 strict 30-second heartbeat}';

    protected $description = 'Audit all drivers for matching eligibility and rejection reasons.';

    public function handle(DriverEligibilityAuditor $auditor): int
    {
        $transport = (string) $this->option('transport');
        $lat = $this->option('lat') !== null ? (float) $this->option('lat') : null;
        $lng = $this->option('lng') !== null ? (float) $this->option('lng') : null;
        $radius = (float) $this->option('radius');
        $strictV3 = ! (bool) $this->option('legacy');

        $drivers = Driver::query()
            ->with(['user', 'vehicles'])
            ->orderBy('id')
            ->get();

        $eligible = 0;
        $rejected = 0;
        $rows = [];

        foreach ($drivers as $driver) {
            $result = $auditor->evaluate($driver, $transport, $lat, $lng, $radius, $strictV3);
            $result['eligible'] ? $eligible++ : $rejected++;

            $rows[] = [
                $driver->id,
                $driver->user?->name ?? 'Unknown',
                $result['eligible'] ? 'YES' : 'NO',
                $result['distance_km'] ?? '-',
                $result['score'],
                $result['reasons'] ? implode('; ', $result['reasons']) : '-',
                $result['warnings'] ? implode('; ', $result['warnings']) : '-',
            ];
        }

        $this->info('RideConnect Matching Readiness Report');
        $this->line('Transport: '.$transport);
        $this->line('Heartbeat mode: '.($strictV3 ? 'V3 strict 30 seconds' : 'Legacy 15 minutes'));
        if ($lat !== null && $lng !== null) {
            $this->line("Pickup radius check: {$lat}, {$lng} within {$radius} km");
        }
        $this->newLine();

        $this->table(
            ['Driver ID', 'Name', 'Eligible', 'Distance km', 'Score', 'Rejection reasons', 'Warnings'],
            $rows
        );

        $this->newLine();
        $this->info("Eligible drivers: {$eligible}");
        $this->warn("Rejected drivers: {$rejected}");
        $this->line('Mandatory criteria: approved driver, active driver if column exists, approved user, online flag, available/online availability, current heartbeat, no current/active trip, no pending assignment lock, compatible active vehicle, location present, inside requested radius when provided.');
        $this->line('Optional criteria: verified user, verified vehicle, fresh driver_locations heartbeat.');
        $this->line('Scoring criteria: local score = 80% distance score plus 20% rating score; ML paths may also use ETA, availability, acceptance rate, and vehicle type features.');

        return self::SUCCESS;
    }
}
