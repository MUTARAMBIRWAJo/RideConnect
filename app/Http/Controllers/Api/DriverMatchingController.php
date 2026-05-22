<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Services\DriverMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DriverMatchingController extends Controller
{
    public function __construct(private readonly DriverMatchingService $driverMatchingService) {}

    public function index(Request $request): JsonResponse
    {
        // Normalize alternate location payload shapes to expected keys
        $payload = $request->all();
        if (! isset($payload['pickup_lat']) && isset($payload['pickup']) && is_array($payload['pickup'])) {
            if (isset($payload['pickup']['lat'])) {
                $request->merge(['pickup_lat' => $payload['pickup']['lat']]);
            }
            if (isset($payload['pickup']['lng'])) {
                $request->merge(['pickup_lng' => $payload['pickup']['lng']]);
            }
            if (isset($payload['pickup']['latitude'])) {
                $request->merge(['pickup_lat' => $payload['pickup']['latitude']]);
            }
            if (isset($payload['pickup']['longitude'])) {
                $request->merge(['pickup_lng' => $payload['pickup']['longitude']]);
            }
        }
        if (! isset($payload['dropoff_lat']) && isset($payload['dropoff']) && is_array($payload['dropoff'])) {
            if (isset($payload['dropoff']['lat'])) {
                $request->merge(['dropoff_lat' => $payload['dropoff']['lat']]);
            }
            if (isset($payload['dropoff']['lng'])) {
                $request->merge(['dropoff_lng' => $payload['dropoff']['lng']]);
            }
            if (isset($payload['dropoff']['latitude'])) {
                $request->merge(['dropoff_lat' => $payload['dropoff']['latitude']]);
            }
            if (isset($payload['dropoff']['longitude'])) {
                $request->merge(['dropoff_lng' => $payload['dropoff']['longitude']]);
            }
        }

        // Accept camelCase variants
        if (! isset($payload['pickup_lat']) && isset($payload['pickupLatitude'])) {
            $request->merge(['pickup_lat' => $payload['pickupLatitude']]);
        }
        if (! isset($payload['pickup_lng']) && isset($payload['pickupLongitude'])) {
            $request->merge(['pickup_lng' => $payload['pickupLongitude']]);
        }
        if (! isset($payload['dropoff_lat']) && isset($payload['dropoffLatitude'])) {
            $request->merge(['dropoff_lat' => $payload['dropoffLatitude']]);
        }
        if (! isset($payload['dropoff_lng']) && isset($payload['dropoffLongitude'])) {
            $request->merge(['dropoff_lng' => $payload['dropoffLongitude']]);
        }

        // Coerce numeric strings to numbers where possible
        foreach (['pickup_lat','pickup_lng','dropoff_lat','dropoff_lng'] as $k) {
            if ($request->has($k) && is_string($request->input($k))) {
                $val = $request->input($k);
                if (is_numeric($val)) {
                    $request->merge([$k => (float) $val]);
                }
            }
        }

        $validator = Validator::make($request->all(), [
            'transport_type' => ['required', 'string', Rule::in([
                'motor_vehicle',
                'private_car',
                'moto',
                'motorbike',
                'motorcycle',
                'car',
                Ride::TRANSPORT_MOTORCYCLE,
                Ride::TRANSPORT_CAR,
            ])],
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'dropoff_lat' => 'required|numeric|between:-90,90',
            'dropoff_lng' => 'required|numeric|between:-180,180',
            'limit' => 'nullable|integer|min:1|max:50',
            'excluded_driver_ids' => 'nullable|array',
            'excluded_driver_ids.*' => 'integer|exists:drivers,id',
        ]);

        $validator->after(function ($validator) use ($request): void {
            $pickupLat = $request->input('pickup_lat');
            $pickupLng = $request->input('pickup_lng');
            $dropoffLat = $request->input('dropoff_lat');
            $dropoffLng = $request->input('dropoff_lng');

            if ($this->hasInvalidRwandaCoordinates($pickupLat, $pickupLng)) {
                $validator->errors()->add('pickup_lat', 'Pickup coordinates must be within Rwanda.');
            }

            if ($this->hasInvalidRwandaCoordinates($dropoffLat, $dropoffLng)) {
                $validator->errors()->add('dropoff_lat', 'Dropoff coordinates must be within Rwanda.');
            }

            if (is_numeric($pickupLat) && is_numeric($pickupLng) && is_numeric($dropoffLat) && is_numeric($dropoffLng)) {
                $distance = $this->haversineDistanceKm((float) $pickupLat, (float) $pickupLng, (float) $dropoffLat, (float) $dropoffLng);
                if ($distance < 0.2) {
                    $validator->errors()->add('dropoff_lat', 'Pickup and dropoff must be at least 200 meters apart.');
                }
                if ($distance > 200) {
                    $validator->errors()->add('dropoff_lat', 'Pickup and dropoff distance is too large for this matching flow.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $transportType = $this->driverMatchingService->normalizeTransportType($validated['transport_type']);

        if (! in_array($transportType, [Ride::TRANSPORT_MOTORCYCLE, Ride::TRANSPORT_CAR], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Driver matching is only available for Motor Vehicle and Private Car transport.',
            ], 422);
        }

        $passengerId = $request->user()?->mobile_user_id ?? $request->user()?->id;

        return response()->json([
            'success' => true,
            'data' => $this->driverMatchingService->match($validated, (int) $passengerId),
        ]);
    }

    private function hasInvalidRwandaCoordinates($lat, $lng): bool
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return true;
        }

        return (float) $lat < -2.9
            || (float) $lat > -1.0
            || (float) $lng < 28.8
            || (float) $lng > 30.9;
    }

    private function haversineDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lng1)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
