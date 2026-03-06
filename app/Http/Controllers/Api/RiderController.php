<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\Trip;
use App\Models\Payment;
use App\Models\DriverDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RiderController extends Controller
{
    /**
     * Update rider availability status.
     * PUT /api/v1/rider/status
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user is a driver
        if ($user->role !== 'driver') {
            return response()->json([
                'success' => false,
                'message' => 'Only drivers can update availability status',
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:online,offline,busy',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        // Update driver status
        $driver->update([
            'status' => $validated['status'],
            'current_latitude' => $validated['latitude'] ?? $driver->current_latitude,
            'current_longitude' => $validated['longitude'] ?? $driver->current_longitude,
            'last_online_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'data' => [
                'status' => $driver->status,
                'latitude' => $driver->current_latitude,
                'longitude' => $driver->current_longitude,
                'last_online_at' => $driver->last_online_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get ride requests for the rider.
     * GET /api/v1/rider/requests
     */
    public function rideRequests(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'driver') {
            return response()->json([
                'success' => false,
                'message' => 'Only drivers can access ride requests',
            ], 403);
        }

        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        // Get pending requests in driver's area (simplified - should use spatial queries)
        $requests = Trip::where('status', 'PENDING')
            ->with(['passenger.user'])
            ->orderBy('requested_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $requests->map(fn($trip) => [
                'id' => $trip->id,
                'passenger' => [
                    'id' => $trip->passenger?->user?->id,
                    'name' => $trip->passenger?->user?->name,
                    'phone' => $trip->passenger?->user?->phone,
                    'rating' => $trip->passenger?->user?->rating,
                ],
                'pickup_location' => $trip->pickup_location,
                'pickup_lat' => $trip->pickup_lat,
                'pickup_lng' => $trip->pickup_lng,
                'dropoff_location' => $trip->dropoff_location,
                'dropoff_lat' => $trip->dropoff_lat,
                'dropoff_lng' => $trip->dropoff_lng,
                'fare' => $trip->fare,
                'distance' => $trip->distance,
                'status' => $trip->status,
                'requested_at' => $trip->requested_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Accept a ride request.
     * PUT /api/v1/rider/requests/{id}/accept
     */
    public function acceptRequest(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'driver') {
            return response()->json([
                'success' => false,
                'message' => 'Only drivers can accept ride requests',
            ], 403);
        }

        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        $trip = Trip::findOrFail($id);

        if ($trip->status !== 'PENDING') {
            return response()->json([
                'success' => false,
                'message' => 'This request is no longer available',
            ], 400);
        }

        // Check if driver is available
        if ($driver->status !== 'online') {
            return response()->json([
                'success' => false,
                'message' => 'You must be online to accept requests',
            ], 400);
        }

        // Accept the trip
        $trip->update([
            'driver_id' => $driver->id,
            'status' => 'ACCEPTED',
            'accepted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ride request accepted',
            'data' => [
                'id' => $trip->id,
                'status' => $trip->status,
                'accepted_at' => $trip->accepted_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Reject a ride request.
     * PUT /api/v1/rider/requests/{id}/reject
     */
    public function rejectRequest(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'driver') {
            return response()->json([
                'success' => false,
                'message' => 'Only drivers can reject ride requests',
            ], 403);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $trip = Trip::findOrFail($id);

        if ($trip->status !== 'PENDING') {
            return response()->json([
                'success' => false,
                'message' => 'This request is no longer available',
            ], 400);
        }

        // Reject the trip
        $trip->update([
            'status' => 'REJECTED',
            'rejection_reason' => $validated['reason'] ?? null,
            'rejected_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ride request rejected',
            'data' => [
                'id' => $trip->id,
                'status' => $trip->status,
            ],
        ]);
    }

    /**
     * Complete a ride request.
     * PUT /api/v1/rider/requests/{id}/complete
     */
    public function completeRequest(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'driver') {
            return response()->json([
                'success' => false,
                'message' => 'Only drivers can complete ride requests',
            ], 403);
        }

        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        $validated = $request->validate([
            'actual_pickup_lat' => 'nullable|numeric|between:-90,90',
            'actual_pickup_lng' => 'nullable|numeric|between:-180,180',
            'actual_dropoff_lat' => 'nullable|numeric|between:-90,90',
            'actual_dropoff_lng' => 'nullable|numeric|between:-180,180',
            'actual_distance' => 'nullable|numeric|min:0',
            'actual_fare' => 'nullable|numeric|min:0',
        ]);

        $trip = Trip::where('driver_id', $driver->id)->findOrFail($id);

        if (!in_array($trip->status, ['ACCEPTED', 'STARTED'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot complete this trip in current status',
            ], 400);
        }

        // Complete the trip
        $trip->update([
            'status' => 'COMPLETED',
            'completed_at' => now(),
            'actual_pickup_lat' => $validated['actual_pickup_lat'] ?? $trip->pickup_lat,
            'actual_pickup_lng' => $validated['actual_pickup_lng'] ?? $trip->pickup_lng,
            'actual_dropoff_lat' => $validated['actual_dropoff_lat'] ?? $trip->dropoff_lat,
            'actual_dropoff_lng' => $validated['actual_dropoff_lng'] ?? $trip->dropoff_lng,
            'actual_distance' => $validated['actual_distance'] ?? $trip->distance,
            'actual_fare' => $validated['actual_fare'] ?? $trip->fare,
        ]);

        // Update driver stats
        $driver->update([
            'total_rides' => $driver->total_rides + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ride completed successfully',
            'data' => [
                'id' => $trip->id,
                'status' => $trip->status,
                'completed_at' => $trip->completed_at?->toIso8601String(),
                'actual_fare' => $trip->actual_fare,
            ],
        ]);
    }

    /**
     * Get rider earnings.
     * GET /api/v1/rider/earnings
     */
    public function earnings(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'driver') {
            return response()->json([
                'success' => false,
                'message' => 'Only drivers can view earnings',
            ], 403);
        }

        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        // Get date range from request
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        // Get completed trips in date range
        $trips = Trip::where('driver_id', $driver->id)
            ->where('status', 'COMPLETED')
            ->whereBetween('completed_at', [$startDate, $endDate]);

        $totalEarnings = $trips->sum('actual_fare');
        $completedTrips = $trips->count();

        // Calculate average fare
        $averageFare = $completedTrips > 0 ? $totalEarnings / $completedTrips : 0;

        // Get today's earnings
        $todayEarnings = Trip::where('driver_id', $driver->id)
            ->where('status', 'COMPLETED')
            ->whereDate('completed_at', today())
            ->sum('actual_fare');

        // Get pending payments
        $pendingPayments = Trip::where('driver_id', $driver->id)
            ->where('status', 'COMPLETED')
            ->whereNull('paid_to_driver_at')
            ->sum('actual_fare');

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'total_earnings' => $totalEarnings,
                'completed_trips' => $completedTrips,
                'average_fare' => round($averageFare, 2),
                'today_earnings' => $todayEarnings,
                'pending_payments' => $pendingPayments,
                    'currency' => 'RWF',
            ],
        ]);
    }

    /**
     * Get monthly earnings.
     * GET /api/v1/rider/earnings/monthly
     */
    public function monthlyEarnings(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'driver') {
            return response()->json([
                'success' => false,
                'message' => 'Only drivers can view earnings',
            ], 403);
        }

        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        // Get last 12 months
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $month = now()->subMonths($i);
            $startOfMonth = $month->copy()->startOfMonth();
            $endOfMonth = $month->copy()->endOfMonth();

            $earnings = Trip::where('driver_id', $driver->id)
                ->where('status', 'COMPLETED')
                ->whereBetween('completed_at', [$startOfMonth, $endOfMonth])
                ->sum('actual_fare');

            $tripsCount = Trip::where('driver_id', $driver->id)
                ->where('status', 'COMPLETED')
                ->whereBetween('completed_at', [$startOfMonth, $endOfMonth])
                ->count();

            $months[] = [
                'month' => $month->format('Y-m'),
                'month_name' => $month->format('F Y'),
                'earnings' => $earnings,
                'trips' => $tripsCount,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $months,
        ]);
    }

    /**
     * Upload rider document.
     * POST /api/v1/rider/documents
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'driver') {
            return response()->json([
                'success' => false,
                'message' => 'Only drivers can upload documents',
            ], 403);
        }

        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        $validated = $request->validate([
            'document_type' => 'required|string|in:license,insurance,registration,id_card,other',
            'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'expiry_date' => 'nullable|date|after:today',
            'notes' => 'nullable|string|max:500',
        ]);

        // Store document
        $path = $request->file('document')->store('driver_documents/' . $driver->id, 'public');

        $document = DriverDocument::create([
            'driver_id' => $driver->id,
            'document_type' => $validated['document_type'],
            'file_path' => $path,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'PENDING',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully',
            'data' => [
                'id' => $document->id,
                'document_type' => $document->document_type,
                'status' => $document->status,
                'uploaded_at' => $document->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * List rider documents.
     * GET /api/v1/rider/documents
     */
    public function listDocuments(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'driver') {
            return response()->json([
                'success' => false,
                'message' => 'Only drivers can view documents',
            ], 403);
        }

        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        $documents = DriverDocument::where('driver_id', $driver->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $documents->map(fn($doc) => [
                'id' => $doc->id,
                'document_type' => $doc->document_type,
                'status' => $doc->status,
                'expiry_date' => $doc->expiry_date?->toIso8601String(),
                'verified_at' => $doc->verified_at?->toIso8601String(),
                'uploaded_at' => $doc->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
