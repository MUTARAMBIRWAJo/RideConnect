<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Carbon;
use App\Models\Driver;
use Illuminate\Support\Facades\Schema;

Carbon::setTestNow(Carbon::parse('2026-05-16 10:00:00'));

Driver::factory()->create(['status' => 'approved']);
Driver::factory()->create(['status' => 'approved']);
Driver::factory()->pending()->create();

Schema::shouldReceive('hasColumn')
    ->once()
    ->with('drivers', 'is_online')
    ->andReturn(false);

$widget = new class extends App\Filament\Widgets\Dashboard\OfficerOverviewStats {
    public function exposedGetStats(): array
    {
        return $this->getStats();
    }
};

$stats = collect($widget->exposedGetStats());
$driversOnlineStat = $stats->first(fn($stat)=> $stat->getLabel() === 'Drivers Online');

echo "Drivers Online value: " . $driversOnlineStat->getValue() . "\n";
