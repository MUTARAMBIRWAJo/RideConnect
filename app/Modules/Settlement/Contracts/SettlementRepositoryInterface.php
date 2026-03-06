<?php

namespace App\Modules\Settlement\Contracts;

use App\Models\DriverPayout;

interface SettlementRepositoryInterface
{
    public function findByDriverAndDate(int $driverId, string $date): ?DriverPayout;

    public function createPayout(array $data): DriverPayout;

    public function getDriversEligibleForSettlement(string $date): \Illuminate\Support\Collection;

    public function sumSettledByDate(string $date): float;

    public function markProcessed(int $payoutId, int $processedBy): void;
}
