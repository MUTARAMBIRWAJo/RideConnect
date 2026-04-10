<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Corridor;
use App\Models\MobileUser;
use App\Models\Ride;
use App\Models\Trip;
use App\Models\User;
use App\Models\Zone;
use App\Services\PassengerRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OfficerBookingTripController extends Controller
{
    /**
     * Get list of passengers for dropdown
     */
    public function getPassengers(Request $request): JsonResponse
    {
        $search = $request->query('search', '');
        
        // Search in both MobileUser and User tables
        $query = MobileUser::query();
        
        if ($search) {
            $query->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
        }
        
        $passengers = $query->select('id', 'first_name', 'last_name', 'email', 'phone')
                            ->limit(50)
                            ->get()
                            ->map(fn($p) => [
                                'id' => $p->id,
                                'name' => "{$p->first_name} {$p->last_name}",
                                'email' => $p->email,
                                'phone' => $p->phone,
                            ]);
        
        return response()->json(['success' => true, 'data' => $passengers]);
    }

    /**
     * Create a new passenger
     */
    public function createPassenger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:mobile_users,email',
            'phone' => 'required|string|max:20|unique:mobile_users,phone',
            'delivery_channel' => 'nullable|string|in:email,sms,whatsapp',
        ]);

        try {
            $fullName = trim($validated['first_name'] . ' ' . $validated['last_name']);
            $user = app(PassengerRegistrationService::class)->createOrUpdatePassenger(
                $fullName,
                $validated['email'],
                $validated['phone'],
                (string) ($validated['delivery_channel'] ?? 'email')
            );

            $passenger = MobileUser::query()->where('email', $validated['email'])->first();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $passenger?->id ?? $user->mobile_user_id,
                    'name' => $passenger ? "{$passenger->first_name} {$passenger->last_name}" : $user->name,
                    'email' => $passenger?->email ?? $user->email,
                    'phone' => $passenger?->phone ?? $user->phone,
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to create passenger'], 500);
        }
    }

    /**
     * Get list of corridors for dropdown
     */
    public function getCorridors(Request $request): JsonResponse
    {
        $search = $request->query('search', '');
        
        $query = Corridor::with('startZone', 'endZone');
        
        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }
        
        $corridors = $query->select('id', 'name', 'start_zone_id', 'end_zone_id', 'base_fare')
                           ->limit(50)
                           ->get()
                           ->map(fn($c) => [
                               'id' => $c->id,
                               'name' => $c->name,
                               'startZone' => $c->startZone?->name,
                               'endZone' => $c->endZone?->name,
                               'baseFare' => $c->base_fare,
                           ]);
        
        return response()->json(['success' => true, 'data' => $corridors]);
    }

    /**
     * Search for locations by name/address
     */
    public function searchLocations(Request $request): JsonResponse
    {
        $search = $request->query('q', '');
        
        if (strlen($search) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        // Search in zones first
        $zones = Zone::where('name', 'LIKE', "%{$search}%")
                     ->select('id', 'name', 'latitude', 'longitude')
                     ->limit(10)
                     ->get()
                     ->map(fn($z) => [
                         'id' => $z->id,
                         'name' => $z->name,
                         'type' => 'zone',
                         'lat' => $z->latitude,
                         'lng' => $z->longitude,
                     ]);

        // If not enough results, try corridor start/end names
        if ($zones->count() < 5) {
            $corridors = Corridor::where('name', 'LIKE', "%{$search}%")
                                  ->with('startZone', 'endZone')
                                  ->select('id', 'name', 'start_zone_id', 'end_zone_id')
                                  ->limit(5)
                                  ->get()
                                  ->flatMap(fn($c) => [
                                      [
                                          'id' => 'start_' . $c->id,
                                          'name' => $c->startZone?->name . ' (Start)',
                                          'type' => 'corridor_start',
                                          'lat' => $c->startZone?->latitude,
                                          'lng' => $c->startZone?->longitude,
                                      ],
                                      [
                                          'id' => 'end_' . $c->id,
                                          'name' => $c->endZone?->name . ' (End)',
                                          'type' => 'corridor_end',
                                          'lat' => $c->endZone?->latitude,
                                          'lng' => $c->endZone?->longitude,
                                      ]
                                  ]);
            
            $zones = $zones->concat($corridors)->unique('name');
        }

        return response()->json(['success' => true, 'data' => $zones->values()]);
    }

    /**
     * Create a booking for a passenger (by officer)
     * NOTE: Officer-created bookings are created without a user_id since officers manage them directly
     * For passengers without existing web accounts, trips are more appropriate
     */
    public function createBooking(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role?->value, ['OFFICER', 'ADMIN', 'SUPER_ADMIN'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'ride_id' => 'nullable|integer|exists:rides,id',
            'pickup_address' => 'required|string|max:255',
            'pickup_lat' => 'required|numeric',
            'pickup_lng' => 'required|numeric',
            'dropoff_address' => 'required|string|max:255',
            'dropoff_lat' => 'required|numeric',
            'dropoff_lng' => 'required|numeric',
            'seats_booked' => 'required|integer|min:1|max:6',
            'special_requests' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            // If ride_id is provided, verify it exists and has available seats
            $ride = null;
            if ($validated['ride_id'] ?? null) {
                $ride = Ride::findOrFail($validated['ride_id']);
                if ($ride->available_seats < $validated['seats_booked']) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'Not enough available seats on this ride'], 422);
                }
            }

            // Create booking without a user_id if not provided (officer-managed)
            // This allows officers to create bookings for passengers who aren't web users yet
            $booking = new Booking();
            $booking->user_id = $validated['user_id'];
            $booking->ride_id = $validated['ride_id'];
            $booking->seats_booked = $validated['seats_booked'];
            $booking->pickup_address = $validated['pickup_address'];
            $booking->pickup_lat = $validated['pickup_lat'];
            $booking->pickup_lng = $validated['pickup_lng'];
            $booking->dropoff_address = $validated['dropoff_address'];
            $booking->dropoff_lat = $validated['dropoff_lat'];
            $booking->dropoff_lng = $validated['dropoff_lng'];
            $booking->special_requests = $validated['special_requests'];
            $booking->status = 'PENDING';
            $booking->total_price = 0; // To be calculated when assigned
            $booking->currency = 'RWF';
            $booking->save();

            // If ride was provided, decrement available seats
            if ($ride) {
                $ride->decrement('available_seats', $validated['seats_booked']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully',
                'data' => [
                    'id' => $booking->id,
                    'status' => $booking->status,
                    'pickup_address' => $booking->pickup_address,
                    'dropoff_address' => $booking->dropoff_address,
                    'seats_booked' => $booking->seats_booked,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to create booking: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Create a trip for a passenger (by officer)
     * Requires driver selection
     */
    public function createTrip(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role?->value, ['ADMIN', 'SUPER_ADMIN'])) {
            return response()->json(['success' => false, 'message' => 'Only Admin/Super Admin can create trips directly'], 403);
        }

        $validated = $request->validate([
            'passenger_id' => 'required|integer|exists:mobile_users,id',
            'driver_id' => 'required|integer|exists:drivers,id',
            'ride_id' => 'required|integer|exists:rides,id',
            'pickup_location' => 'required|string|max:255',
            'pickup_lat' => 'required|numeric',
            'pickup_lng' => 'required|numeric',
            'pickup_zone' => 'nullable|string|max:100',
            'dropoff_location' => 'required|string|max:255',
            'dropoff_lat' => 'required|numeric',
            'dropoff_lng' => 'required|numeric',
            'dropoff_zone' => 'nullable|string|max:100',
            'fare' => 'required|numeric|min:0',
            'special_requests' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            // Get the ride to verify it exists and driver can serve it
            $ride = Ride::findOrFail($validated['ride_id']);
            
            if ($ride->available_seats < 1) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'No available seats on this ride'], 422);
            }

            $trip = Trip::create([
                'ride_id' => $validated['ride_id'],
                'passenger_id' => $validated['passenger_id'],
                'driver_id' => $validated['driver_id'],
                'pickup_location' => $validated['pickup_location'],
                'pickup_lat' => $validated['pickup_lat'],
                'pickup_lng' => $validated['pickup_lng'],
                'pickup_zone' => $validated['pickup_zone'],
                'dropoff_location' => $validated['dropoff_location'],
                'dropoff_lat' => $validated['dropoff_lat'],
                'dropoff_lng' => $validated['dropoff_lng'],
                'dropoff_zone' => $validated['dropoff_zone'],
                'fare' => $validated['fare'],
                'status' => 'ACCEPTED', // Officer-created trips start as accepted
                'requested_at' => now(),
                'accepted_at' => now(),
            ]);

            // Decrement available seats on the ride
            $ride->decrement('available_seats');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Trip created successfully',
                'data' => [
                    'id' => $trip->id,
                    'passenger_id' => $trip->passenger_id,
                    'driver_id' => $trip->driver_id,
                    'status' => $trip->status,
                    'fare' => $trip->fare,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to create trip: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get available rides matching location criteria
     */
    public function getAvailableRides(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'corridor_id' => 'nullable|integer',
            'start_zone_id' => 'nullable|integer',
            'end_zone_id' => 'nullable|integer',
        ]);

        $query = Ride::where('status', 'PUBLISHED')
                     ->where('available_seats', '>', 0)
                     ->with('driver', 'zone', 'corridor');

        if ($validated['corridor_id'] ?? null) {
            $query->where('corridor_id', $validated['corridor_id']);
        }

        if ($validated['start_zone_id'] ?? null) {
            $query->where(function($q) use ($validated) {
                $q->where('zone_id', $validated['start_zone_id']);
            });
        }

        $rides = $query->select([
            'id', 'driver_id', 'corridor_id', 'zone_id',
            'origin_address', 'destination_address',
            'departure_time', 'available_seats', 'price_per_seat'
        ])
        ->orderBy('departure_time')
        ->limit(50)
        ->get()
        ->map(fn($r) => [
            'id' => $r->id,
            'driver_name' => $r->driver?->user?->name,
            'from' => $r->origin_address,
            'to' => $r->destination_address,
            'departure_time' => $r->departure_time,
            'available_seats' => $r->available_seats,
            'price_per_seat' => $r->price_per_seat,
        ]);

        return response()->json(['success' => true, 'data' => $rides]);
    }
}
