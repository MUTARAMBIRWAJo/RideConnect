<?php

namespace App\Services\Ml;

use Carbon\CarbonInterface;

class DemandHeuristicModelV1
{
    public const MODEL_NAME = 'DemandHeuristicModelV1';

    public const MODEL_VERSION = 'v1';

    public const ENDPOINT = '/ml/predict-demand';

    public function payload(array $input, ?CarbonInterface $time = null): array
    {
        $time ??= now();

        return [
            'latitude' => (float) ($input['latitude'] ?? $input['lat'] ?? -1.944),
            'longitude' => (float) ($input['longitude'] ?? $input['lng'] ?? 30.061),
            'hour' => (int) ($input['hour'] ?? $input['time_of_day'] ?? $time->hour),
            'day_of_week' => (int) ($input['day_of_week'] ?? $time->dayOfWeek),
        ];
    }
}
