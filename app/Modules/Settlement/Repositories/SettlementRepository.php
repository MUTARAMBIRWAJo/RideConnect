<?php

namespace App\Modules\Settlement\Repositories;

use App\Models\Driver;
use App\Models\DriverPayout;
use App\Modules\Settlement\Contracts\SettlementRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SettlementRepository implements SettlementRepositoryInterface
{
    public function findByDriverAndDate(int $driverId, string $date): ?DriverPayout
    {
        return DriverPayout::where('driver_id', $driverId)
            ->whereDate('payout_date', $date)
            ->first();
    }

    public function createPayout(array $data): DriverPayout
    {
        return DriverPayout::create($data);
    }

    public function getDriversEligibleForSettlement(string $date): Collection
    {
        // Drivers with completed rides on the given date who have NOT been settled yet
        return Driver::whereHas('rides', fn ($q) => $q
            ->where('status', 'completed')
            ->whereDate('updated_at', $date)
        )
        ->whereDoesntHave('payouts', fn ($q) => $q->whereDate('payout_date', $date))
        ->get();
    }

    public function sumSettledByDate(string $date): float
    {
        return (float) DriverPayout::whereDate('payout_date', $date)
            ->where('status', 'processed')
            ->sum('payout_amount');
    }

    public function markProcessed(int $payoutId, int $processedBy): void
    {
        DriverPayout::where('id', $payoutId)->update([
            'status'       => 'processed',
            'processed_by' => $processedBy,
            'processed_at' => now(),
        ]);
    }
}
