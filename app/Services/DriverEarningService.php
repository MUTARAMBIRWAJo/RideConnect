<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DriverEarningService
{
    public const COMMISSION_RATE = 0.08;

    public function calculateDriverDailyIncome(int $driverId, string $date): array
    {
        $targetDate = Carbon::parse($date)->toDateString();

        $bookings = Booking::query()
            ->with(['ride', 'payment'])
            ->whereHas('ride', function ($query) use ($driverId) {
                $query->where('driver_id', $driverId)
                    ->whereIn('status', ['COMPLETED', 'completed']);
            })
            ->whereHas('payment', function ($query) use ($targetDate) {
                $query->whereRaw('LOWER(status) = ?', ['completed'])
                    ->whereRaw('COALESCE(paid_at, created_at)::date = ?', [$targetDate]);
            })
            ->whereIn('status', ['CONFIRMED', 'confirmed', 'COMPLETED', 'completed'])
            ->get();

        $totalIncome = (float) $bookings->sum('total_price');
        $commission = $this->calculateCommission($totalIncome);
        $payout = $this->calculatePayout($totalIncome);

        return [
            'date' => $targetDate,
            'driver_id' => $driverId,
            'total_driver_income' => round($totalIncome, 2),
            'commission' => $commission,
            'payout_amount' => $payout,
            'ride_ids' => $bookings->pluck('ride_id')->filter()->unique()->values()->all(),
            'booking_ids' => $bookings->pluck('id')->values()->all(),
            'bookings' => $bookings,
        ];
    }

    public function calculateCommission(float $amount): float
    {
        return round($amount * self::COMMISSION_RATE, 2);
    }

    public function calculatePayout(float $amount): float
    {
        return round($amount - $this->calculateCommission($amount), 2);
    }

    public function calculateManyDrivers(array $driverIds, string $date): Collection
    {
        return collect($driverIds)->mapWithKeys(function ($driverId) use ($date) {
            return [(int) $driverId => $this->calculateDriverDailyIncome((int) $driverId, $date)];
        });
    }
}
