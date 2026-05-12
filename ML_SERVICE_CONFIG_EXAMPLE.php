/**
 * Laravel Services Configuration Example
 * 
 * This shows how to configure the ML microservice integration in your Laravel application.
 * Copy the ml_service section to your config/services.php file.
 */

return [
    // ... other services

    'ml_service' => [
        'url' => env('ML_SERVICE_URL', 'http://localhost:8000'),
        'timeout' => env('ML_SERVICE_TIMEOUT', 30),
        'enabled' => env('ML_SERVICE_ENABLED', true),
    ],

    // ... other services
];

/**
 * .env Configuration
 * 
 * Add these to your .env file:
 */
/*
ML_SERVICE_URL=http://ml-service:8000
ML_SERVICE_TIMEOUT=30
ML_SERVICE_ENABLED=true
*/

/**
 * Usage in Your Application
 * 
 * Example 1: Driver Matching in RideController
 * 
 * use App\Services\MLPredictionService;
 * 
 * class RideController extends Controller
 * {
 *     protected $mlService;
 *     
 *     public function __construct(MLPredictionService $mlService)
 *     {
 *         $this->mlService = $mlService;
 *     }
 *     
 *     public function createRide(Request $request)
 *     {
 *         // Get candidate drivers from database
 *         $candidates = Driver::getActiveDrivers($request->input('latitude'), $request->input('longitude'));
 *         
 *         // Convert to ML service format
 *         $candidateDrivers = $candidates->map(function ($driver) {
 *             return [
 *                 'driver_id' => $driver->id,
 *                 'distance_km' => $driver->distance_from_request,
 *                 'driver_rating' => $driver->rating,
 *                 'acceptance_rate' => $driver->acceptance_rate,
 *                 'cancellation_rate' => $driver->cancellation_rate,
 *                 'behavior_score' => $driver->behavior_score,
 *                 'available_seats' => $driver->vehicle->seats,
 *                 'traffic_level' => $this->getTrafficLevel($driver->latitude, $driver->longitude),
 *                 'direction_similarity' => $this->calculateDirectionSimilarity($driver),
 *             ];
 *         })->toArray();
 *         
 *         // Call ML service
 *         $rideRequest = [
 *             'pickup_latitude' => $request->input('pickup_latitude'),
 *             'pickup_longitude' => $request->input('pickup_longitude'),
 *             'destination_latitude' => $request->input('destination_latitude'),
 *             'destination_longitude' => $request->input('destination_longitude'),
 *             'requested_vehicle_type' => $request->input('vehicle_type'),
 *             'required_seats' => $request->input('passengers'),
 *         ];
 *         
 *         $matchResult = $this->mlService->matchDriver($rideRequest, $candidateDrivers);
 *         
 *         // Assign best driver
 *         $ride = Ride::create($request->validated());
 *         $ride->assignDriver($matchResult['best_driver']['driver_id']);
 *         
 *         return response()->json(['ride' => $ride]);
 *     }
 * }
 */

/**
 * Example 2: Demand Prediction for Peak Hour Analysis
 * 
 * use App\Services\MLPredictionService;
 * 
 * class AnalyticsController extends Controller
 * {
 *     public function predictDemand()
 *     {
 *         $mlService = new MLPredictionService();
 *         
 *         $demand = $mlService->predictDemand(
 *             latitude: -1.9441,
 *             longitude: 30.0619,
 *             hour: now()->hour,
 *             dayOfWeek: now()->dayOfWeek
 *         );
 *         
 *         return response()->json([
 *             'current_demand' => $demand['demand_level'],
 *             'wait_time' => $demand['expected_wait_time_minutes'],
 *             'confidence' => $demand['confidence'],
 *         ]);
 *     }
 * }
 */

/**
 * Example 3: ETA Calculation with ML Enhancement
 * 
 * use App\Services\MLPredictionService;
 * 
 * class ETACalculator
 * {
 *     protected $mlService;
 *     
 *     public function __construct(MLPredictionService $mlService)
 *     {
 *         $this->mlService = $mlService;
 *     }
 *     
 *     public function calculateETA($trip)
 *     {
 *         // Get traffic data
 *         $traffic = $this->getTrafficLevel(
 *             $trip->pickup_latitude,
 *             $trip->pickup_longitude
 *         );
 *         
 *         // Get ML prediction
 *         $eta = $this->mlService->predictETA(
 *             pickupLatitude: $trip->pickup_latitude,
 *             pickupLongitude: $trip->pickup_longitude,
 *             destinationLatitude: $trip->destination_latitude,
 *             destinationLongitude: $trip->destination_longitude,
 *             trafficLevel: $traffic,
 *             distanceKm: $trip->distance_km
 *         );
 *         
 *         $trip->update([
 *             'estimated_arrival' => now()->addMinutes($eta['estimated_time_minutes']),
 *             'confidence' => $eta['confidence'],
 *         ]);
 *         
 *         return $eta;
 *     }
 * }
 */

/**
 * Example 4: Error Handling and Fallback
 * 
 * use App\Services\MLPredictionService;
 * use Illuminate\Support\Facades\Log;
 * 
 * class SafeMLWrapper
 * {
 *     protected $mlService;
 *     protected $defaultStrategy;
 *     
 *     public function __construct(MLPredictionService $mlService)
 *     {
 *         $this->mlService = $mlService;
 *     }
 *     
 *     public function matchDriverSafely($rideRequest, $candidates)
 *     {
 *         try {
 *             // Check service health
 *             if (!$this->mlService->isHealthy()) {
 *                 Log::warning('ML service is not healthy, using fallback matching');
 *                 return $this->fallbackMatching($candidates);
 *             }
 *             
 *             // Perform ML matching
 *             return $this->mlService->matchDriver($rideRequest, $candidates);
 *             
 *         } catch (\Exception $e) {
 *             Log::error('ML service matching failed', [
 *                 'error' => $e->getMessage(),
 *                 'candidates' => count($candidates),
 *             ]);
 *             
 *             // Fall back to simple distance-based matching
 *             return $this->fallbackMatching($candidates);
 *         }
 *     }
 *     
 *     private function fallbackMatching($candidates)
 *     {
 *         // Sort by distance (closest first)
 *         $sorted = collect($candidates)
 *             ->sortBy('distance_km')
 *             ->take(5);
 *         
 *         return [
 *             'best_driver' => [
 *                 'driver_id' => $sorted->first()['driver_id'],
 *                 'score' => 0.5,  // Fallback score
 *             ],
 *             'ranked_drivers' => $sorted->map(fn ($d) => [
 *                 'driver_id' => $d['driver_id'],
 *                 'score' => 0.5 - (count($d) * 0.01),
 *             ])->toArray(),
 *         ];
 *     }
 * }
 */
