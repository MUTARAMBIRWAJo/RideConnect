<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Trip;
use App\Models\Driver;
use Illuminate\Http\Request;

class AdminReviewController extends Controller
{
    /**
     * GET /admin/reviews
     * Paginated, filterable review list.
     */
    public function index(Request $request)
    {
        $query = Review::with(['driver.user', 'trip'])
            ->latest('created_at');

        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }
        if ($request->filled('reviewer_type')) {
            // reviewer_type: 'passenger' | 'driver'
            $query->where('reviewer_type', $request->reviewer_type);
        }
        if ($request->filled('is_public')) {
            $query->where('is_public', (bool) $request->is_public);
        }
        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }

        $reviews  = $query->paginate(20)->withQueryString();
        $drivers  = Driver::with('user')->where('status', 'approved')
                          ->orderBy('rating', 'desc')->get(['id', 'user_id', 'rating']);

        // Enum options — MUST match Flutter RatingDialog values
        $reviewerTypes = ['passenger', 'driver'];

        return view('admin.reviews.index', compact('reviews', 'drivers', 'reviewerTypes'));
    }

    /**
     * GET /admin/reviews/{review}
     * Full review detail.
     */
    public function show(Review $review)
    {
        $review->load(['driver.user', 'trip']);
        return view('admin.reviews.show', compact('review'));
    }

    /**
     * GET /admin/reviews/create
     * Admin can manually create a review for a completed trip.
     */
    public function create(Request $request)
    {
        $trips   = Trip::where('status', 'completed')->latest()->get(['id', 'passenger_id', 'driver_id']);
        $drivers = Driver::with('user')->where('status', 'approved')->get();

        // Enum options — MUST match Flutter
        $reviewerTypes = ['passenger' => 'Passenger', 'driver' => 'Driver'];

        return view('admin.reviews.create', compact('trips', 'drivers', 'reviewerTypes'));
    }

    /**
     * POST /admin/reviews
     */
    public function store(Request $request)
    {
        // Validation MUST match API ReviewController (if it exists) exactly.
        // Field names identical to Flutter RatingDialog JSON keys.
        $validated = $request->validate([
            'trip_id'                  => 'required|integer|exists:trips,id',
            'driver_id'                => 'required|integer|exists:drivers,id',
            'user_id'                  => 'required|integer|exists:mobile_users,id',
            'ride_id'                  => 'required|integer',
            'booking_id'               => 'required|integer',
            'rating'                   => 'required|integer|min:1|max:5',
            'reviewer_type'            => 'required|in:passenger,driver',
            'comment'                  => 'nullable|string|max:1000',
            'safety_rating'            => 'nullable|integer|min:1|max:5',
            'punctuality_rating'       => 'nullable|integer|min:1|max:5',
            'communication_rating'     => 'nullable|integer|min:1|max:5',
            'vehicle_condition_rating' => 'nullable|integer|min:1|max:5',
            'is_public'                => 'boolean',
        ]);

        $review = Review::create($validated + ['is_public' => $request->boolean('is_public', true)]);

        // Update driver avg rating after new review
        $avg = Review::where('driver_id', $validated['driver_id'])->avg('rating');
        $count = Review::where('driver_id', $validated['driver_id'])->count();
        Driver::where('id', $validated['driver_id'])->update([
            'rating'       => round($avg, 2),
            'rating_count' => $count,
        ]);

        return redirect()->route('admin.reviews.show', $review)
            ->with('success', "Review #{$review->id} created.");
    }

    /**
     * DELETE /admin/reviews/{review}
     * Hide/delete an abusive review.
     */
    public function destroy(Review $review)
    {
        $review->update(['is_public' => false]);
        return redirect()->route('admin.reviews.index')
            ->with('success', "Review #{$review->id} hidden.");
    }
}
