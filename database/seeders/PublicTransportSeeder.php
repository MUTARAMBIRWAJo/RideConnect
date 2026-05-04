<?php

namespace Database\Seeders;

use App\Models\Corridor;
use App\Models\RouteStop;
use App\Models\TransportRoute;
use App\Models\RuraTariff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PublicTransportSeeder extends Seeder
{
    public function run(): void
    {
        $policyRows = $this->loadPolicyRows();

        foreach ($policyRows as $row) {
            $corridorCode = strtoupper(trim((string) ($row['corridor_code'] ?? $row['corridor'] ?? '')));

            if ($corridorCode === '') {
                continue;
            }

            $corridor = Corridor::query()->updateOrCreate(
                ['code' => $corridorCode],
                [
                    'name' => (string) ($row['corridor_name'] ?? ('Corridor ' . $corridorCode)),
                    'kinyarwanda_name' => $row['kinyarwanda_name'] ?? null,
                ]
            );

            $routeCode = (string) ($row['route_code'] ?? '');
            if ($routeCode === '') {
                continue;
            }

            [$origin, $destination] = $this->deriveOriginDestination($row);
            $routeName = (string) ($row['name'] ?? trim($origin . ' → ' . $destination));

            $route = TransportRoute::query()->updateOrCreate(
                [
                    'corridor_id' => $corridor->id,
                    'route_code' => $routeCode,
                ],
                [
                    'name' => $routeName,
                    'via' => $row['via'] ?? null,
                    'origin' => $origin,
                    'destination' => $destination,
                    'is_active' => (bool) ($row['is_active'] ?? true),
                ]
            );

            $stops = $this->deriveStops($row, $origin, $destination);
            foreach ($stops as $index => $stopName) {
                RouteStop::query()->updateOrCreate(
                    [
                        'route_id' => $route->id,
                        'stop_order' => $index + 1,
                    ],
                    [
                        'stop_name' => $stopName,
                        'lat' => null,
                        'lng' => null,
                    ]
                );
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadPolicyRows(): array
    {
        $jsonPath = base_path('database/seeders/data/public_transport_policy.json');

        if (File::exists($jsonPath)) {
            $decoded = json_decode((string) File::get($jsonPath), true);

            return is_array($decoded) ? $decoded : [];
        }

        return RuraTariff::query()
            ->orderBy('corridor')
            ->orderBy('route_code')
            ->get()
            ->map(fn (RuraTariff $tariff): array => [
                'corridor_code' => $tariff->corridor,
                'corridor_name' => 'Corridor ' . strtoupper((string) $tariff->corridor),
                'route_code' => $tariff->route_code,
                'name' => trim((string) $tariff->origin_stop . ' → ' . (string) $tariff->destination_stop),
                'origin' => $tariff->origin_stop,
                'destination' => $tariff->destination_stop,
                'is_active' => true,
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $row
     * @return array{0: string, 1: string}
     */
    private function deriveOriginDestination(array $row): array
    {
        $origin = (string) ($row['origin'] ?? $row['origin_stop'] ?? '');
        $destination = (string) ($row['destination'] ?? $row['destination_stop'] ?? '');

        if ($origin !== '' && $destination !== '') {
            return [$origin, $destination];
        }

        $name = (string) ($row['name'] ?? '');
        $parts = preg_split('/\s*(?:→|->|-)\s*/', $name) ?: [];

        return [
            trim((string) ($parts[0] ?? '')),
            trim((string) (Arr::last($parts) ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<int, string>
     */
    private function deriveStops(array $row, string $origin, string $destination): array
    {
        if (! empty($row['stops']) && is_array($row['stops'])) {
            return array_values(array_filter(array_map('strval', $row['stops'])));
        }

        $stops = [$origin];

        if (! empty($row['via'])) {
            $stops[] = (string) $row['via'];
        }

        $stops[] = $destination;

        return array_values(array_filter(array_unique(array_map('trim', $stops))));
    }
}