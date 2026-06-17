<?php

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$baseUrl = 'https://rideconnect-emp0.onrender.com/api/v1';

echo "Testing RideConnect Backend on $baseUrl\n";
echo str_repeat('-', 50) . "\n";

function request($method, $url, $data = [], $token = null) {
    global $baseUrl;
    $request = Http::acceptJson()->timeout(15);
    if ($token) {
        $request = $request->withToken($token);
    }
    
    try {
        if ($method === 'GET') {
            $response = $request->get($baseUrl . $url, $data);
        } else if ($method === 'POST') {
            $response = $request->post($baseUrl . $url, $data);
        } else if ($method === 'PUT') {
            $response = $request->put($baseUrl . $url, $data);
        }
        
        return [
            'status' => $response->status(),
            'body' => $response->json() ?? $response->body(),
        ];
    } catch (\Exception $e) {
        return [
            'status' => 500,
            'body' => ['error' => $e->getMessage()]
        ];
    }
}

// 1. Register Passenger
$passengerPhone = '+250' . rand(780000000, 789999999);
$passengerEmail = 'passenger_' . time() . '@test.com';
echo "1. Registering Passenger ($passengerPhone)...\n";
$res = request('POST', '/auth/register/passenger', [
    'name' => 'Test Passenger',
    'email' => $passengerEmail,
    'password' => 'password123',
    'password_confirmation' => 'password123',
    'phone' => $passengerPhone,
    'role' => 'PASSENGER',
]);
if ($res['status'] !== 201) {
    echo "Failed to register passenger: " . json_encode($res['body']) . "\n";
    exit(1);
}

// 2. Login Passenger
echo "2. Logging in Passenger...\n";
$res = request('POST', '/auth/mobile/login', [
    'email' => $passengerEmail,
    'password' => 'password123',
    'device_id' => 'test_device_passenger',
]);
if ($res['status'] !== 200) {
    echo "Failed to login passenger: " . json_encode($res['body']) . "\n";
    exit(1);
}
$passengerToken = $res['body']['data']['token'];

// 3. Register Driver
$driverPhone = '+250' . rand(780000000, 789999999);
$driverEmail = 'driver_' . time() . '@test.com';
echo "3. Registering Driver ($driverPhone)...\n";
$res = request('POST', '/auth/register/driver', [
    'name' => 'Test Driver',
    'email' => $driverEmail,
    'password' => 'password123',
    'password_confirmation' => 'password123',
    'phone' => $driverPhone,
    'role' => 'DRIVER',
]);
if ($res['status'] !== 201) {
    echo "Failed to register driver: " . json_encode($res['body']) . "\n";
    exit(1);
}

// 4. Login Driver
echo "4. Logging in Driver...\n";
$res = request('POST', '/auth/mobile/login', [
    'email' => $driverEmail,
    'password' => 'password123',
    'device_id' => 'test_device_driver',
]);
if ($res['status'] !== 200) {
    echo "Failed to login driver: " . json_encode($res['body']) . "\n";
    exit(1);
}
$driverToken = $res['body']['data']['token'];

echo "\n!!! Production Database Limitation !!!\n";
echo "The test script created a passenger and a driver.\n";
echo "However, a driver MUST be 'approved' and have a linked 'vehicle' to be eligible for trip matching.\n";
echo "Since we cannot auto-approve and inject a vehicle via public mobile APIs without Admin endpoints,\n";
echo "the matching engine will ignore this newly created driver, resulting in NO DRIVERS FOUND for the passenger.\n";
echo "To fully test on production, we need a PRE-EXISTING test driver token with a vehicle, OR the flutter app must test it directly.\n\n";

// Let's attempt anyway to see the passenger flow:
echo "5. Passenger Requesting Trip...\n";
$res = request('POST', '/passenger/motor-vehicle/trip-requests', [
    'pickup_lat' => -1.9579,
    'pickup_lng' => 30.1127,
    'dropoff_lat' => -1.9580,
    'dropoff_lng' => 30.1130,
], $passengerToken);
echo "Status: " . $res['status'] . "\n";
echo "Response: " . json_encode($res['body']) . "\n";

if ($res['status'] === 201) {
    $tripId = $res['body']['data']['trip_id'] ?? null;
    
    // Poll the trip
    echo "6. Passenger Polling Trip (Should be MATCHING_PENDING)...\n";
    $poll = request('GET', '/passenger/motor-vehicle/trip-requests/current', [], $passengerToken);
    echo "Status: " . $poll['status'] . "\n";
    echo "Response: " . json_encode($poll['body']) . "\n";
    
    if ($tripId) {
        echo "7. Passenger Cancelling Trip...\n";
        $cancel = request('POST', "/passenger/motor-vehicle/trip-requests/{$tripId}/cancel", [], $passengerToken);
        echo "Status: " . $cancel['status'] . "\n";
        echo "Response: " . json_encode($cancel['body']) . "\n";
    }
}
