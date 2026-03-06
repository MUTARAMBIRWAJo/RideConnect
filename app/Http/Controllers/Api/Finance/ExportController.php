<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Api\Concerns\TracksAdminActivity;
use App\Http\Controllers\Controller;
use App\Http\Resources\Finance\FinanceExportResultResource;
use App\Models\Manager;
use App\Models\Payment;
use App\Models\Trip;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ExportController extends Controller
{
    use TracksAdminActivity;

    private const TICKET_THRESHOLD_HOURS = 6;

    /**
     * Export financial reports.
     * GET /api/v1/finance/export
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('exportFinance', Manager::class);

        $validated = Validator::make($request->all(), [
            'format' => ['nullable', 'in:csv,pdf'],
            'type' => ['nullable', 'in:transactions,revenue,driver_earnings'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'booking_type' => ['nullable', 'in:ticket,booking'],
        ])->validate();

        $format = $validated['format'] ?? 'csv';
        $type = $validated['type'] ?? 'transactions';
        $startDate = $validated['start_date'] ?? now()->startOfMonth();
        $endDate = $validated['end_date'] ?? now()->endOfMonth();
        $bookingType = $validated['booking_type'] ?? null;

        $data = [];

        switch ($type) {
            case 'transactions':
                $data = $this->exportTransactions($startDate, $endDate, $bookingType);
                break;
            case 'revenue':
                $data = $this->exportRevenue($startDate, $endDate, $bookingType);
                break;
            case 'driver_earnings':
                $data = $this->exportDriverEarnings($startDate, $endDate, $bookingType);
                break;
            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid export type',
                ], 422);
        }

        if ($format === 'csv') {
            $result = $this->generateCsvExport($data, $type);
            $this->trackAdminActivity($user, 'finance_export_csv', "Exported {$type} report as CSV");

            return response()->json([
                'success' => true,
                'data' => new FinanceExportResultResource($result),
            ]);
        }

        if ($format === 'pdf') {
            $result = $this->generatePdfExport($data, $type);
            $this->trackAdminActivity($user, 'finance_export_pdf', "Exported {$type} report as PDF");

            return response()->json([
                'success' => true,
                'data' => new FinanceExportResultResource($result),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unsupported export format',
        ], 400);
    }

    /**
     * Export as PDF.
     * GET /api/v1/finance/export/pdf
     */
    public function exportPdf(Request $request): JsonResponse
    {
        $request->merge(['format' => 'pdf']);

        return $this->export($request);
    }

    /**
     * Export as CSV.
     * GET /api/v1/finance/export/csv
     */
    public function exportCsv(Request $request): JsonResponse
    {
        return $this->export($request);
    }

    /**
     * Export transactions data.
     */
    private function exportTransactions($startDate, $endDate, ?string $bookingType = null): array
    {
        $query = Payment::whereBetween('created_at', [$startDate, $endDate])
            ->with(['user', 'booking.ride'])
            ->orderBy('created_at', 'desc')
            ;

        $this->applyBookingTypeFilter($query, $bookingType);

        $transactions = $query->get();

        return $transactions->map(function ($payment) {
            return [
                'ID' => $payment->id,
                'Date' => $payment->created_at->format('Y-m-d H:i:s'),
                'User' => $payment->user?->name ?? 'N/A',
                'User Email' => $payment->user?->email ?? 'N/A',
                'Type' => $payment->type,
                'Amount' => $payment->amount,
                'Currency' => $payment->currency,
                'Status' => $payment->status,
                'Payment Method' => $payment->payment_method,
                'Transaction ID' => $payment->transaction_id ?? 'N/A',
                'Travel Type' => $this->resolveTravelTypeByPayment($payment),
            ];
        })->toArray();
    }

    /**
     * Export revenue data.
     */
    private function exportRevenue($startDate, $endDate, ?string $bookingType = null): array
    {
        $query = Payment::whereRaw('LOWER(status) = ?', ['completed'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        $this->applyBookingTypeFilter($query, $bookingType);

        // Daily revenue breakdown
        $dailyRevenue = $query
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as total_revenue'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return $dailyRevenue->map(function ($row) {
            return [
                'Date' => $row->date,
                'Total Revenue' => $row->total_revenue,
                'Transaction Count' => $row->transaction_count,
                'Average Transaction' => $row->transaction_count > 0 
                    ? $row->total_revenue / $row->transaction_count 
                    : 0,
            ];
        })->toArray();
    }

    private function applyBookingTypeFilter($query, ?string $bookingType): void
    {
        if (! $bookingType) {
            return;
        }

        $normalizedType = strtolower(trim($bookingType));
        $threshold = now()->copy()->addHours(self::TICKET_THRESHOLD_HOURS);

        if ($normalizedType === 'ticket') {
            $query->whereHas('booking.ride', fn ($rideQuery) => $rideQuery->where('departure_time', '<=', $threshold));
        }

        if ($normalizedType === 'booking') {
            $query->whereHas('booking.ride', fn ($rideQuery) => $rideQuery->where('departure_time', '>', $threshold));
        }
    }

    private function resolveTravelTypeByPayment(Payment $payment): string
    {
        $departure = $payment->booking?->ride?->departure_time;

        if (! $departure) {
            return 'BOOKING';
        }

        $hoursToDeparture = now()->diffInMinutes($departure, false) / 60;

        return $hoursToDeparture <= self::TICKET_THRESHOLD_HOURS ? 'TICKET' : 'BOOKING';
    }

    /**
     * Export driver earnings data.
     */
    private function exportDriverEarnings($startDate, $endDate, ?string $bookingType = null): array
    {
        $tripQuery = Trip::where('status', 'COMPLETED')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->with(['driver.user'])
            ->orderBy('completed_at', 'desc');

        // Apply booking window filtering only when trip-booking linkage exists.
        if ($bookingType && Schema::hasColumn('trips', 'booking_id')) {
            $normalizedType = strtolower(trim($bookingType));
            $threshold = now()->copy()->addHours(self::TICKET_THRESHOLD_HOURS);

            if ($normalizedType === 'ticket') {
                $tripQuery->whereExists(function ($query) use ($threshold) {
                    $query->select(DB::raw(1))
                        ->from('bookings')
                        ->join('rides', 'rides.id', '=', 'bookings.ride_id')
                        ->whereColumn('bookings.id', 'trips.booking_id')
                        ->where('rides.departure_time', '<=', $threshold);
                });
            }

            if ($normalizedType === 'booking') {
                $tripQuery->whereExists(function ($query) use ($threshold) {
                    $query->select(DB::raw(1))
                        ->from('bookings')
                        ->join('rides', 'rides.id', '=', 'bookings.ride_id')
                        ->whereColumn('bookings.id', 'trips.booking_id')
                        ->where('rides.departure_time', '>', $threshold);
                });
            }
        }

        $trips = $tripQuery->get();

        return $trips->map(function ($trip) {
            return [
                'Trip ID' => $trip->id,
                'Date' => $trip->completed_at?->format('Y-m-d H:i:s') ?? 'N/A',
                'Driver' => $trip->driver?->user?->name ?? 'N/A',
                'Driver Email' => $trip->driver?->user?->email ?? 'N/A',
                'Fare' => $trip->fare,
                'Actual Fare' => $trip->actual_fare ?? $trip->fare,
                'Distance' => $trip->distance ?? 'N/A',
                'Status' => $trip->status,
            ];
        })->toArray();
    }

    /**
     * Generate CSV export.
     */
    private function generateCsvExport(array $data, string $type): array
    {
        if (empty($data)) {
            return [
                'filename' => null,
                'path' => null,
                'url' => null,
                'records' => 0,
                'type' => $type,
                'format' => 'csv',
                'generated_at' => now()->toIso8601String(),
            ];
        }

        // Generate CSV content
        $csv = implode(",", array_keys($data[0])) . "\n";
        
        foreach ($data as $row) {
            $csv .= implode(",", array_map(function ($value) {
                return is_numeric($value) ? $value : '"' . str_replace('"', '""', $value) . '"';
            }, $row)) . "\n";
        }

        // Store file
        $filename = $type . '_' . now()->format('Y-m-d_His') . '.csv';
        $path = 'exports/' . $filename;
        Storage::put($path, $csv);

        return [
            'filename' => $filename,
            'path' => $path,
            'url' => Storage::url($path),
            'records' => count($data),
            'type' => $type,
            'format' => 'csv',
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function generatePdfExport(array $data, string $type): array
    {
        $filename = $type . '_' . now()->format('Y-m-d_His') . '.pdf';
        $path = 'exports/' . $filename;

        $headers = !empty($data) ? array_keys($data[0]) : ['Message'];
        $rows = !empty($data) ? $data : [['Message' => 'No data available for selected filters.']];

        $html = '<h2>Finance Export: ' . e($type) . '</h2><table border="1" cellpadding="6" cellspacing="0" width="100%"><thead><tr>';

        foreach ($headers as $header) {
            $html .= '<th>' . e((string) $header) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($headers as $header) {
                $html .= '<td>' . e((string) ($row[$header] ?? '')) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
        Storage::put($path, $pdf->output());

        return [
            'filename' => $filename,
            'path' => $path,
            'url' => Storage::url($path),
            'records' => count($data),
            'type' => $type,
            'format' => 'pdf',
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
