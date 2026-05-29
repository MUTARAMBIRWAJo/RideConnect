<?php

namespace Database\Seeders;

use App\Models\MobileTripE2eUseCase;
use Illuminate\Database\Seeder;

class MobileTripE2eUseCaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->useCases() as $useCase) {
            MobileTripE2eUseCase::query()->updateOrCreate(
                ['slug' => $useCase['slug']],
                $useCase
            );
        }
    }

    private function useCases(): array
    {
        return [
            $this->publicTransport(),
            $this->privateTransport(),
            $this->motoTransport(),
        ];
    }

    private function publicTransport(): array
    {
        return [
            'slug' => 'public-bus-nyabugogo-remera',
            'title' => 'Public Transport Bus: Nyabugogo Bus Park to Remera',
            'transport_type' => 'BUS',
            'passenger_page' => 'PublicTransportTripPage',
            'passenger_flow' => [
                'actor' => ['name' => 'Aline Uwimana', 'phone' => '+250788111222'],
                'steps' => [
                    'Open PublicTransportTripPage and verify Google Places Autocomplete renders pickup and destination fields.',
                    'Select Nyabugogo Bus Park as pickup and Remera as destination.',
                    'Choose Bus, set seat count to 2, departure time to 08:30 AM, payment method to Mobile Money.',
                    'Enter note: We are carrying two small bags and prefer front seats.',
                    'Verify route preview map draws Nyabugogo to Remera polyline through Kigali city corridors.',
                    'Submit trip request and wait for backend matching session, driver assignment attempt, and push notification delivery.',
                    'Acknowledge driver selection, watch live marker updates, confirm pickup, complete trip, payment, and rating.',
                ],
                'inputs' => [
                    'pickup' => ['label' => 'Nyabugogo Bus Park', 'lat' => -1.939826, 'lng' => 30.044542],
                    'destination' => ['label' => 'Remera', 'lat' => -1.953564, 'lng' => 30.109842],
                    'vehicle_type' => 'Bus',
                    'seat_count' => 2,
                    'departure_time' => '08:30',
                    'payment_method' => 'mobile_money',
                    'notes' => 'We are carrying two small bags and prefer front seats.',
                ],
            ],
            'driver_flow' => [
                'actor' => ['name' => 'Jean Claude', 'phone' => '+250788333444'],
                'page' => 'IncomingTripRequestPage',
                'inputs' => [
                    'decision_accept' => ['action' => 'ACCEPT', 'response_time_seconds' => 18],
                    'decision_reject' => ['action' => 'REJECT', 'reason' => 'Vehicle already full after station boarding'],
                ],
                'expected_screen' => [
                    'passenger_name' => 'Aline Uwimana',
                    'passenger_phone' => '+250788111222',
                    'pickup_location' => 'Nyabugogo Bus Park',
                    'destination' => 'Remera',
                    'estimated_fare' => '2,500 RWF',
                    'estimated_distance' => '8.2 km',
                    'eta_to_pickup' => '12 min',
                    'seat_requirement' => 2,
                    'countdown_timer_seconds' => 45,
                    'actions' => ['ACCEPT', 'REJECT'],
                ],
            ],
            'api_payloads' => [
                'POST /api/v1/passenger/trip-requests' => [
                    'ride_id' => 184,
                    'requested_seats' => 2,
                    'pickup_location' => 'Nyabugogo Bus Park',
                    'pickup_lat' => -1.939826,
                    'pickup_lng' => 30.044542,
                    'dropoff_location' => 'Remera',
                    'dropoff_lat' => -1.953564,
                    'dropoff_lng' => 30.109842,
                    'payment_method' => 'mobile_money',
                    'passenger_notes' => 'We are carrying two small bags and prefer front seats.',
                ],
                'POST /api/v1/mobile/drivers/trips/{id}/accept' => ['driver_acknowledgement' => true],
                'POST /api/v1/mobile/drivers/live-location' => [
                    'trip_id' => '{trip_id}',
                    'lat' => -1.941612,
                    'lng' => 30.057944,
                    'speed_kmh' => 28,
                    'heading' => 83,
                    'accuracy' => 7,
                    'is_online' => true,
                ],
                'POST /api/v1/notifications/{id}/acknowledged' => ['source' => 'fcm', 'device_id' => 'aline-pixel-8'],
                'POST /api/v1/trips/{id}/acknowledge' => ['acknowledgement_type' => 'passenger_driver_selected'],
            ],
            'api_responses' => [
                'trip_request_created' => ['success' => true, 'data' => ['status' => 'PENDING', 'assignment_status' => 'notified', 'payment_status' => 'pending']],
                'status' => ['success' => true, 'data' => ['trip_status' => 'ACCEPTED', 'timeline' => ['Requested', 'AI Matching', 'Driver Selected', 'Driver Accepted']]],
                'matching_session' => ['status' => 'selected', 'selected_driver_id' => '{jean_claude_driver_id}', 'confidence' => 0.88],
            ],
            'expected_ui' => [
                'widgets' => ['Google Places Autocomplete', 'Seat Selector', 'Route Preview Map', 'Fare Estimation Card', 'ETA Widget', 'Passenger Notes'],
                'driver_card' => [
                    'driver_name' => 'Jean Claude',
                    'driver_rating' => 4.8,
                    'vehicle' => 'Toyota Coaster',
                    'vehicle_plate_number' => 'RAA-123-B',
                    'available_seats' => 2,
                    'driver_distance' => '2.4 km away',
                    'eta' => '12 min',
                    'estimated_fare' => '2,500 RWF',
                    'driver_live_location' => ['lat' => -1.941612, 'lng' => 30.057944],
                ],
                'timeline' => [
                    ['label' => 'Requested', 'checked' => true],
                    ['label' => 'AI Matching', 'checked' => true],
                    ['label' => 'Driver Selected', 'checked' => true],
                    ['label' => 'Driver Accepted', 'checked' => true],
                    ['label' => 'Driver Arriving', 'checked' => false],
                    ['label' => 'Picked Up', 'checked' => false],
                    ['label' => 'In Progress', 'checked' => false],
                    ['label' => 'Completed', 'checked' => false],
                ],
            ],
            'notifications' => [
                'driver_push' => [
                    'title' => 'New Public Trip Request',
                    'body' => 'Passenger: Aline Uwimana Pickup: Nyabugogo Destination: Remera Seats: 2 Estimated Fare: 2,500 RWF ETA to Passenger: 12 min',
                ],
                'passenger_pushes' => ['Driver found', 'Driver accepted', 'Driver arriving', 'Trip started', 'Trip completed', 'Payment confirmed'],
            ],
            'matching_engine_results' => [
                'candidate_count' => 5,
                'selected_driver' => 'Jean Claude',
                'ranking_score' => 0.8842,
                'score_breakdown' => ['distance' => 0.31, 'rating' => 0.22, 'capacity' => 0.2, 'route_fit' => 0.1342],
                'ml_service' => ['endpoint' => '/api/v1/ml/rank-drivers', 'version' => 'ranker-kigali-v1'],
            ],
            'tracking_updates' => [
                ['lat' => -1.941612, 'lng' => 30.057944, 'eta_minutes' => 12, 'checkpoint' => 'Nyabugogo approach'],
                ['lat' => -1.943804, 'lng' => 30.072501, 'eta_minutes' => 8, 'checkpoint' => 'Downtown Kigali corridor'],
                ['lat' => -1.953564, 'lng' => 30.109842, 'eta_minutes' => 0, 'checkpoint' => 'Remera dropoff'],
            ],
            'backend_validation' => [
                'assertions' => ['trip_status = DRIVER_CONFIRMED or ACCEPTED', 'matching_session.selected_driver_id is set', 'trip_assignment_attempts.status = accepted', 'notification_deliveries.acknowledged_at is populated'],
            ],
            'database_validation' => [
                'tables' => ['trips', 'bookings', 'seat_reservations', 'matching_sessions', 'trip_assignment_attempts', 'user_notifications', 'notification_deliveries', 'trip_status_events', 'payments', 'reviews'],
            ],
            'failure_simulations' => [
                'driver_rejection' => 'Driver declines; backend clears driver_id, creates trip_rejections row, passenger sees Driver unavailable and Searching for another driver.',
                'no_drivers_available' => 'ML returns empty candidate list; passenger sees no buses available and retry CTA.',
                'notification_failure' => 'Push bridge fails; user_notifications remains persisted and notification_deliveries.status = failed.',
            ],
            'correction_prompts' => [
                'Passenger trip status stuck at ML_MATCHING' => ['inspect websocket listener', 'inspect polling logic', 'inspect trip state synchronization', 'inspect matching-session endpoint', 'inspect provider/bloc state update', 'inspect notification acknowledgement handling'],
                'Driver accepts trip but passenger UI not updating' => ['inspect realtime subscription', 'inspect trip status API', 'inspect socket event handling', 'inspect backend acknowledgement persistence', 'inspect notification routing', 'inspect state rebuild logic'],
            ],
            'pass_fail_validations' => [
                'pass' => ['Trip, matching session, assignment attempt, notification delivery, status event, payment, and rating records are consistent.'],
                'fail' => ['Any missing persisted record, stale timeline step, route marker freeze, duplicate active assignment, or unacknowledged required notification.'],
            ],
            'is_active' => true,
        ];
    }

    private function privateTransport(): array
    {
        return $this->onDemandUseCase(
            'private-sedan-kcc-kacyiru',
            'Private Transport Sedan: Kigali Convention Centre to Kacyiru',
            'PrivateTransportTripPage',
            'CAR',
            ['name' => 'Nadia Iradukunda', 'phone' => '+250788555666'],
            ['label' => 'Kigali Convention Centre', 'lat' => -1.953606, 'lng' => 30.092693],
            ['label' => 'Kacyiru', 'lat' => -1.935114, 'lng' => 30.082111],
            ['name' => 'Eric Nshimiyimana', 'phone' => '+250788777888', 'rating' => 4.9, 'vehicle' => 'Toyota Corolla', 'plate' => 'RAB-908-K'],
            5500,
            5,
            0.91,
            ['Traffic Conditions', 'Route Map', 'Expected Earnings', 'Estimated Trip Duration', 'Emergency Support']
        );
    }

    private function motoTransport(): array
    {
        return $this->onDemandUseCase(
            'moto-kimironko-downtown',
            'Moto Transport: Kimironko to Downtown Kigali',
            'MotoTripPage',
            'MOTORCYCLE',
            ['name' => 'Patrick Niyonzima', 'phone' => '+250788999000'],
            ['label' => 'Kimironko', 'lat' => -1.948736, 'lng' => 30.126401],
            ['label' => 'Downtown Kigali', 'lat' => -1.944072, 'lng' => 30.061885],
            ['name' => 'Samuel Mugisha', 'phone' => '+250788222333', 'rating' => 4.7, 'vehicle' => 'Yamaha FZ', 'plate' => 'RM-442-X'],
            3200,
            3,
            0.94,
            ['Helmet Reminder', 'Route Preview', 'Cancellation Handling', 'Dispute Reporting', 'Emergency Support']
        );
    }

    private function onDemandUseCase(
        string $slug,
        string $title,
        string $page,
        string $transportType,
        array $passenger,
        array $pickup,
        array $dropoff,
        array $driver,
        int $fare,
        int $eta,
        float $confidence,
        array $driverScreenFields
    ): array {
        $isMoto = $transportType === 'MOTORCYCLE';

        return [
            'slug' => $slug,
            'title' => $title,
            'transport_type' => $transportType,
            'passenger_page' => $page,
            'passenger_flow' => [
                'actor' => $passenger,
                'steps' => [
                    "Open {$page}, select {$pickup['label']} as pickup and {$dropoff['label']} as destination.",
                    'Submit immediate trip request and verify ML matching returns traffic-aware ranked driver.',
                    'Verify driver accepted notification, live GPS tracking, passenger acknowledgement, and trip lifecycle updates.',
                    'Complete trip with payment processing, rating submission, analytics update, and persisted audit events.',
                ],
                'inputs' => [
                    'pickup' => $pickup,
                    'destination' => $dropoff,
                    'vehicle_type' => $isMoto ? 'Moto' : 'Sedan',
                    'trip_type' => 'Immediate',
                    'payment_method' => $isMoto ? 'mobile_money' : 'card',
                    'helmet_requirement' => $isMoto,
                ],
            ],
            'driver_flow' => [
                'actor' => $driver,
                'page' => 'IncomingTripRequestPage',
                'expected_screen' => array_merge([
                    'passenger_name' => $passenger['name'],
                    'phone_number' => $passenger['phone'],
                    'pickup' => $pickup['label'],
                    'destination' => $dropoff['label'],
                    'fare' => number_format($fare).' RWF',
                    'eta_to_pickup' => "{$eta} min",
                ], array_fill_keys($driverScreenFields, true)),
            ],
            'api_payloads' => [
                'GET /api/v1/passenger/drivers/match' => ['transport_type' => $isMoto ? 'moto' : 'private_car', 'pickup_lat' => $pickup['lat'], 'pickup_lng' => $pickup['lng'], 'dropoff_lat' => $dropoff['lat'], 'dropoff_lng' => $dropoff['lng'], 'limit' => 5],
                'POST /api/v1/mobile/trips/request' => ['transport_type' => $isMoto ? 'moto' : 'private_car', 'driver_id' => '{selected_driver_id}', 'matching_session_id' => '{matching_session_id}', 'pickup_location' => $pickup['label'], 'pickup_lat' => $pickup['lat'], 'pickup_lng' => $pickup['lng'], 'dropoff_location' => $dropoff['label'], 'dropoff_lat' => $dropoff['lat'], 'dropoff_lng' => $dropoff['lng'], 'fare' => $fare],
                'POST /api/v1/mobile/drivers/trips/{id}/accept' => ['driver_acknowledgement' => true],
                'POST /api/v1/mobile/drivers/live-location' => ['trip_id' => '{trip_id}', 'lat' => $pickup['lat'], 'lng' => $pickup['lng'], 'speed_kmh' => $isMoto ? 36 : 24, 'heading' => 270, 'accuracy' => 5],
            ],
            'api_responses' => [
                'matching' => ['drivers' => [['driver_name' => $driver['name'], 'rating' => $driver['rating'], 'estimated_arrival_minutes' => $eta, 'estimated_fare' => $fare, 'matching_confidence' => $confidence]]],
                'accept' => ['status' => 'success', 'data' => ['trip_state' => 'ACCEPTED']],
                'complete' => ['status' => 'success', 'data' => ['trip_state' => 'COMPLETED']],
            ],
            'expected_ui' => [
                'driver_card' => [
                    'driver_name' => $driver['name'],
                    'driver_rating' => $driver['rating'],
                    'vehicle' => $driver['vehicle'],
                    'plate_number' => $driver['plate'],
                    'eta' => "{$eta} min",
                    'fare_estimate' => number_format($fare).' RWF',
                    'driver_live_location' => ['visible_on_map' => true],
                    'matching_confidence' => (int) round($confidence * 100).'%',
                    'traffic_status' => $isMoto ? 'Moderate' : 'Traffic-aware routing active',
                    'driver_distance' => $isMoto ? '1.2 km away' : null,
                ],
            ],
            'notifications' => [
                'passenger_pushes' => ['Driver found', 'Driver accepted', 'Driver arriving', 'Trip started', 'Trip completed', 'Payment confirmed'],
                'driver_pushes' => ['New trip request', 'Passenger cancelled', 'Route updated', 'Trip completed'],
            ],
            'matching_engine_results' => [
                'selected_driver' => $driver['name'],
                'ranking_score' => $confidence,
                'traffic_aware_routing' => true,
                'fallback' => 'If ML service unavailable, use nearest eligible online driver with active compatible vehicle.',
            ],
            'tracking_updates' => [
                ['lat' => $pickup['lat'], 'lng' => $pickup['lng'], 'eta_minutes' => $eta],
                ['lat' => round(($pickup['lat'] + $dropoff['lat']) / 2, 6), 'lng' => round(($pickup['lng'] + $dropoff['lng']) / 2, 6), 'eta_minutes' => max(1, $eta - 2)],
                ['lat' => $dropoff['lat'], 'lng' => $dropoff['lng'], 'eta_minutes' => 0],
            ],
            'backend_validation' => [
                'assertions' => ['matching session selected_driver_id persists', 'trip status transitions PENDING -> ACCEPTED -> STARTED -> COMPLETED', 'driver live location row updates', 'payment and analytics records created'],
            ],
            'database_validation' => [
                'tables' => ['trips', 'matching_sessions', 'trip_assignment_attempts', 'driver_locations', 'user_notifications', 'notification_deliveries', 'trip_status_events', 'payments', 'reviews'],
            ],
            'failure_simulations' => [
                'network_timeout' => 'App retries idempotent request with same key and renders non-blocking retry state.',
                'gps_failure' => 'Tracking falls back to last known driver_locations row and shows stale-location banner.',
                'ml_unavailable' => 'Backend selects nearest eligible driver and marks matching source as fallback.',
                'duplicate_assignment' => 'Second accept attempt returns 409 and no duplicate active assignment is persisted.',
            ],
            'correction_prompts' => [
                'Driver accepts trip but passenger UI not updating' => ['inspect realtime subscription', 'inspect trip status API', 'inspect socket event handling', 'inspect backend acknowledgement persistence', 'inspect notification routing', 'inspect state rebuild logic'],
                'Live marker stops moving' => ['inspect live-location endpoint response', 'inspect driver_locations updated_at', 'inspect map provider marker stream', 'inspect low-network fallback timer'],
            ],
            'pass_fail_validations' => [
                'pass' => ['UI, API, ML result, push, live tracking, payment, rating, and database state agree for the same trip id.'],
                'fail' => ['Any stale status, unpersisted acknowledgement, missing notification delivery, route polyline failure, or duplicate assignment.'],
            ],
            'is_active' => true,
        ];
    }
}
