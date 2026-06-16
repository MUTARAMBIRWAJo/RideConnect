<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverBehavior;
use App\Models\MotorcycleTrip;
use App\Models\Review;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /**
     * Submit a rating for a trip or motorcycle trip.
     * This directly updates the DriverBehavior metrics used by the ML models.
     */
    public function store(Request $request, $tripId)
    {
        $validated = $request->validate([
            'type' => 'required|in:standard,motorcycle',
            'rating' => 'required|integer|min:1|max:5',
            'safety_rating' => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        // Retrieve trip
        if ($validated['type'] === 'standard') {
            $trip = Trip::where('id', $tripId)->where('passenger_id', $user->id)->firstOrFail();
            $driverId = $trip->driver_id;
            $rideColumn = 'ride_id';
        } else {
            $trip = MotorcycleTrip::where('id', $tripId)->where('passenger_id', $user->id)->firstOrFail();
            $driverId = $trip->driver_id;
            $rideColumn = 'motorcycle_trip_id';
        }

        if ($trip->status !== 'completed' && $trip->status !== 'COMPLETED') {
            return response()->json(['status' => 'error', 'message' => 'Trip must be completed to leave a rating.'], 400);
        }

        if (!$driverId) {
            return response()->json(['status' => 'error', 'message' => 'No driver assigned to this trip.'], 400);
        }

        // Prevent duplicate reviews
        $existing = Review::where('user_id', $user->id)->where($rideColumn, $tripId)->first();
        if ($existing) {
            return response()->json(['status' => 'error', 'message' => 'You have already rated this trip.'], 400);
        }

        DB::beginTransaction();
        try {
            // Create review
            $review = Review::create([
                'user_id' => $user->id,
                'driver_id' => $driverId,
                $rideColumn => $tripId,
                'rating' => $validated['rating'],
                'safety_rating' => $validated['safety_rating'] ?? $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'reviewer_type' => 'passenger',
                'is_public' => true,
            ]);

            // ML Feedback Loop: Update Driver aggregate rating
            $driver = Driver::find($driverId);
            if ($driver) {
                // Calculate new average rating
                $avgRating = Review::where('driver_id', $driverId)
                    ->where('reviewer_type', 'passenger')
                    ->avg('rating');
                
                $driver->update([
                    'rating' => round($avgRating, 2),
                ]);

                // Update DriverBehavior for ML Models
                DriverBehavior::updateOrCreate(
                    ['driver_id' => $driverId],
                    ['reviewed_at' => now()]
                );
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Rating submitted successfully. Thank you for your feedback!',
                'review' => $review
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to submit rating.'], 500);
        }
    }
}
