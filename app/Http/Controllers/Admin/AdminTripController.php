<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\MobileUser;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminTripController extends Controller
{
    /**
     * GET /admin/trips
     * Paginated, filterable trip list.
     */
    public function index(Request $request)
    {
        $query = Trip::with(['passenger', 'driver.user'])
            ->latest('created_at');

        // Filters (same fields Flutter displays in the UI)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('transport_type')) {
            $query->where('transport_type', $request->transport_type);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('pickup_location',  'like', "%{$request->search}%")
                  ->orWhere('dropoff_location','like', "%{$request->search}%");
            });
        }

        $trips = $query->paginate(20)->withQueryString();

        $tripStats = [
            'total' => Trip::count(),
            'requested' => Trip::whereIn('status', ['requested', 'pending'])->count(),
            'in_progress' => Trip::whereIn('status', ['accepted', 'enroute_to_pickup', 'arrived_at_pickup', 'in_progress'])->count(),
            'completed' => Trip::where('status', 'completed')->count(),
        ];

        // Enum option lists — identical to Flutter dropdown values
        $statusOptions = [
            'requested', 'assigning', 'accepted', 'enroute_to_pickup',
            'arrived_at_pickup', 'in_progress', 'completed', 'cancelled',
        ];
        $transportOptions  = ['moto', 'car', 'bus'];
        $paymentStatuses   = ['unpaid', 'paid', 'refunded'];

        return view('admin.trips.index', compact(
            'trips', 'tripStats', 'statusOptions', 'transportOptions', 'paymentStatuses'
        ));
    }

    /**
     * GET /admin/trips/create
     * Admin-initiated trip creation form — same fields as Flutter BookRideScreen.
     */
    public function create()
    {
        $passengers = MobileUser::where('role', 'passenger')
            ->where('is_verified', true)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'phone']);

        $drivers = Driver::with('user')
            ->where('status', 'approved')
            ->where('availability_status', 'online')
            ->orderBy('rating', 'desc')
            ->get();

        // Enum options — MUST stay in sync with Flutter
        $transportOptions = [
            'moto' => '🏍️ Moto',
            'car'  => '🚗 Car',
            'bus'  => '🚌 Bus',
        ];
        $paymentOptions = [
            'cash' => 'Cash',
            'momo' => 'MoMo',
            'card' => 'Card',
        ];

        return view('admin.trips.create', compact(
            'passengers', 'drivers', 'transportOptions', 'paymentOptions'
        ));
    }

    /**
     * POST /admin/trips
     * Store admin-created trip — same validation as TripController::store() API.
     */
    public function store(Request $request)
    {
        // Validation rules MUST match TripController::store() validation exactly
        $validated = $request->validate([
            'passenger_id'      => 'required|integer|exists:mobile_users,id',
            'pickup_location'   => 'required|string|min:3|max:500',
            'dropoff_location'  => 'required|string|min:3|max:500',
            'pickup_lat'        => 'required|numeric|between:-90,90',
            'pickup_lng'        => 'required|numeric|between:-180,180',
            'dropoff_lat'       => 'required|numeric|between:-90,90',
            'dropoff_lng'       => 'required|numeric|between:-180,180',
            'transport_type'    => 'required|in:moto,car,bus',
            'payment_method'    => 'required|in:cash,momo,card',
            'pickup_place_name' => 'nullable|string|max:255',
            'dropoff_place_name'=> 'nullable|string|max:255',
            'pickup_zone'       => 'nullable|string|max:64',
            'dropoff_zone'      => 'nullable|string|max:64',
            'fare'              => 'required|numeric|min:0',
            'driver_id'         => 'nullable|integer|exists:drivers,id',
        ]);

        $trip = DB::transaction(function () use ($validated) {
            $trip = Trip::create([
                'passenger_id'       => $validated['passenger_id'],
                'pickup_location'    => $validated['pickup_location'],
                'dropoff_location'   => $validated['dropoff_location'],
                'pickup_lat'         => $validated['pickup_lat'],
                'pickup_lng'         => $validated['pickup_lng'],
                'dropoff_lat'        => $validated['dropoff_lat'],
                'dropoff_lng'        => $validated['dropoff_lng'],
                'transport_type'     => $validated['transport_type'],
                'pickup_place_name'  => $validated['pickup_place_name'] ?? null,
                'dropoff_place_name' => $validated['dropoff_place_name'] ?? null,
                'pickup_zone'        => $validated['pickup_zone'] ?? null,
                'dropoff_zone'       => $validated['dropoff_zone'] ?? null,
                'fare'               => $validated['fare'],
                'driver_id'          => $validated['driver_id'] ?? null,
                'status'             => 'requested',
                'payment_status'     => 'unpaid',
                'assignment_status'  => $validated['driver_id'] ? 'assigned' : 'unassigned',
                'rejected_drivers_count' => 0,
                'requested_at'       => now(),
            ]);

            // Log status event (same as API TripController)
            if (method_exists($trip, 'statusEvents')) {
                $trip->statusEvents()->create([
                    'actor_type' => 'admin',
                    'actor_id'   => auth()->id(),
                    'old_status' => null,
                    'new_status' => 'requested',
                    'metadata'   => json_encode(['source' => 'admin_blade']),
                    'created_at' => now(),
                ]);
            }

            return $trip;
        });

        return redirect()
            ->route('admin.trips.show', $trip)
            ->with('success', "Trip #{$trip->id} created successfully.");
    }

    /**
     * GET /admin/trips/{trip}
     * Full trip detail — shows all fields including those set by Flutter.
     */
    public function show(Trip $trip)
    {
        $trip->load([
            'passenger',
            'driver.user',
        ]);

        if (method_exists($trip, 'statusEvents')) {
            $trip->load('statusEvents');
        }

        $availableDrivers = Driver::with('user')
            ->where('status', 'approved')
            ->where('availability_status', 'online')
            ->where('id', '!=', $trip->driver_id)
            ->get();

        return view('admin.trips.show', compact('trip', 'availableDrivers'));
    }

    /**
     * GET /admin/trips/{trip}/edit
     * Edit form — admin can correct any field.
     */
    public function edit(Trip $trip)
    {
        $passengers = MobileUser::where('role', 'passenger')->orderBy('first_name')->get();
        $drivers    = Driver::with('user')->where('status', 'approved')->get();

        $transportOptions = ['moto' => '🏍️ Moto', 'car' => '🚗 Car', 'bus' => '🚌 Bus'];
        $paymentOptions   = ['cash' => 'Cash', 'momo' => 'MoMo', 'card' => 'Card'];
        $statusOptions    = [
            'requested', 'assigning', 'accepted', 'enroute_to_pickup',
            'arrived_at_pickup', 'in_progress', 'completed', 'cancelled',
        ];
        $paymentStatuses  = ['unpaid', 'paid', 'refunded'];
        $assignmentStatuses = ['unassigned', 'assigning', 'assigned', 'failed'];

        return view('admin.trips.edit', compact(
            'trip', 'passengers', 'drivers',
            'transportOptions', 'paymentOptions',
            'statusOptions', 'paymentStatuses', 'assignmentStatuses'
        ));
    }

    /**
     * PUT /admin/trips/{trip}
     */
    public function update(Request $request, Trip $trip)
    {
        $validated = $request->validate([
            'pickup_location'    => 'required|string|min:3|max:500',
            'dropoff_location'   => 'required|string|min:3|max:500',
            'pickup_lat'         => 'required|numeric|between:-90,90',
            'pickup_lng'         => 'required|numeric|between:-180,180',
            'dropoff_lat'        => 'required|numeric|between:-90,90',
            'dropoff_lng'        => 'required|numeric|between:-180,180',
            'transport_type'     => 'required|in:moto,car,bus',
            'pickup_place_name'  => 'nullable|string|max:255',
            'dropoff_place_name' => 'nullable|string|max:255',
            'pickup_zone'        => 'nullable|string|max:64',
            'dropoff_zone'       => 'nullable|string|max:64',
            'fare'               => 'required|numeric|min:0',
            'status'             => 'required|in:requested,assigning,accepted,enroute_to_pickup,arrived_at_pickup,in_progress,completed,cancelled',
            'payment_status'     => 'required|in:unpaid,paid,refunded',
            'assignment_status'  => 'required|in:unassigned,assigning,assigned,failed',
            'driver_id'          => 'nullable|integer|exists:drivers,id',
            'admin_completion_reason' => 'nullable|string|max:500',
        ]);

        $oldStatus = $trip->status;
        $trip->update($validated);

        // Log status change if status changed
        if ($oldStatus !== $validated['status'] && method_exists($trip, 'statusEvents')) {
            $trip->statusEvents()->create([
                'actor_type' => 'admin',
                'actor_id'   => auth()->id(),
                'old_status' => $oldStatus,
                'new_status' => $validated['status'],
                'metadata'   => json_encode(['source' => 'admin_blade', 'reason' => $validated['admin_completion_reason'] ?? null]),
                'created_at' => now(),
            ]);
        }

        return redirect()
            ->route('admin.trips.show', $trip)
            ->with('success', "Trip #{$trip->id} updated.");
    }
}
