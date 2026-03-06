<?php

namespace App\Http\Controllers\Api\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverPayout;
use App\Models\PlatformCommission;
use App\Services\AccountantPayoutService;
use App\Services\DriverEarningService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PayoutController extends Controller
{
    public function __construct(
        private readonly DriverEarningService $earningService,
        private readonly AccountantPayoutService $payoutService,
    ) {
    }

    public function dailyEarnings(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DriverPayout::class);

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:paid,unpaid'],
        ]);

        $date = isset($validated['date']) ? Carbon::parse($validated['date'])->toDateString() : now()->toDateString();

        $drivers = Driver::query()->with('user')->orderBy('id')->get();

        $rows = $drivers->map(function (Driver $driver) use ($date) {
            $income = $this->earningService->calculateDriverDailyIncome($driver->id, $date);
            $payout = DriverPayout::query()
                ->where('driver_id', $driver->id)
                ->whereDate('payout_date', $date)
                ->first();

            return [
                'driver_id' => $driver->id,
                'driver_name' => $driver->user?->name,
                'date' => $date,
                'total_income' => $income['total_driver_income'],
                'commission' => $income['commission'],
                'net_payout' => $income['payout_amount'],
                'status' => $payout?->status === 'processed' ? 'paid' : 'unpaid',
                'payout_id' => $payout?->id,
            ];
        });

        if (($validated['status'] ?? null) === 'paid') {
            $rows = $rows->where('status', 'paid')->values();
        }

        if (($validated['status'] ?? null) === 'unpaid') {
            $rows = $rows->where('status', 'unpaid')->values();
        }

        $totalCommission = $rows->sum('commission');
        $totalPayout = $rows->sum('net_payout');

        return response()->json([
            'success' => true,
            'data' => $rows,
            'summary' => [
                'date' => $date,
                'drivers_count' => $rows->count(),
                'total_commission' => round((float) $totalCommission, 2),
                'total_payout' => round((float) $totalPayout, 2),
            ],
        ]);
    }

    public function payout(Request $request, Driver $driver): JsonResponse
    {
        Gate::authorize('process', DriverPayout::class);

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        try {
            $payout = $this->payoutService->processSingleDriverPayout(
                $driver->id,
                $validated['date'],
                (int) $request->user()->id,
            );

            return response()->json([
                'success' => true,
                'message' => 'Driver payout processed successfully.',
                'data' => [
                    'payout_id' => $payout->id,
                    'driver_id' => $payout->driver_id,
                    'date' => Carbon::parse($payout->payout_date)->toDateString(),
                    'total_income' => (float) $payout->total_income,
                    'commission_amount' => (float) $payout->commission_amount,
                    'payout_amount' => (float) $payout->payout_amount,
                    'status' => $payout->status,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function bulkPayout(Request $request): JsonResponse
    {
        Gate::authorize('process', DriverPayout::class);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'driver_ids' => ['required', 'array', 'min:1'],
            'driver_ids.*' => ['integer', 'exists:drivers,id'],
        ]);

        try {
            $results = $this->payoutService->processBulkPayout(
                $validated['driver_ids'],
                $validated['date'],
                (int) $request->user()->id,
            );

            return response()->json([
                'success' => true,
                'message' => 'Bulk payouts processed successfully.',
                'data' => [
                    'processed_count' => $results->count(),
                    'total_income' => round((float) $results->sum('total_income'), 2),
                    'total_commission' => round((float) $results->sum('commission_amount'), 2),
                    'total_payout' => round((float) $results->sum('payout_amount'), 2),
                    'payout_ids' => $results->pluck('id')->values(),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function exportDailyEarningsCsv(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', DriverPayout::class);

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();

        $rows = Driver::query()->with('user')->orderBy('id')->get()->map(function (Driver $driver) use ($date) {
            $income = $this->earningService->calculateDriverDailyIncome($driver->id, $date);
            $paid = DriverPayout::query()
                ->where('driver_id', $driver->id)
                ->whereDate('payout_date', $date)
                ->where('status', 'processed')
                ->exists();

            return [
                'Driver Name' => $driver->user?->name,
                'Date' => $date,
                'Total Income' => number_format((float) $income['total_driver_income'], 2, '.', ''),
                'Commission (8%)' => number_format((float) $income['commission'], 2, '.', ''),
                'Net Payout (92%)' => number_format((float) $income['payout_amount'], 2, '.', ''),
                'Status' => $paid ? 'Paid' : 'Unpaid',
            ];
        });

        $filename = 'driver-daily-earnings-' . $date . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Driver Name', 'Date', 'Total Income', 'Commission (8%)', 'Net Payout (92%)', 'Status']);

            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function commissionSummary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DriverPayout::class);

        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])->toDateString()
            : now()->startOfMonth()->toDateString();

        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])->toDateString()
            : now()->toDateString();

        $totalCommission = PlatformCommission::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('commission_amount');

        $daily = PlatformCommission::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->select('date', DB::raw('SUM(commission_amount) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'total_commission' => round((float) $totalCommission, 2),
                'daily' => $daily->map(fn ($row) => [
                    'date' => $row->date,
                    'commission' => round((float) $row->total, 2),
                ]),
            ],
        ]);
    }
}
