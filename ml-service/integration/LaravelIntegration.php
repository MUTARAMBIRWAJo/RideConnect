<?php
namespace RideConnect\Integration;

use GuzzleHttp\Client;

class LaravelIntegration
{
    protected $client;

    public function __construct(string $mlBaseUrl)
    {
        $this->client = new Client(['base_uri' => $mlBaseUrl, 'timeout' => 5.0]);
    }

    public function matchDrivers(array $rideRequest, array $candidates): array
    {
        $resp = $this->client->post('/api/predict/match-driver', [
            'json' => ['ride_request' => $rideRequest, 'candidate_drivers' => $candidates]
        ]);
        return json_decode((string)$resp->getBody(), true);
    }
}
