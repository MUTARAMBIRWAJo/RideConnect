<?php

namespace App\Console\Commands;

use App\Services\RideAIService;
use Illuminate\Console\Command;

class RetrainAIModels extends Command
{
    protected $signature = 'ai:retrain-models {--model=* : Optional list of models to retrain}';

    protected $description = 'Trigger AI service retraining pipeline';

    public function handle(RideAIService $rideAIService): int
    {
        $models = array_values(array_filter((array) $this->option('model')));

        $result = $rideAIService->triggerRetrain([
            'models' => $models,
            'triggered_by' => 'laravel_scheduler',
            'requested_at' => now()->toIso8601String(),
        ]);

        if (!($result['success'] ?? false)) {
            $this->error('Failed to trigger retraining: ' . ($result['error'] ?? 'unknown error'));

            return self::FAILURE;
        }

        $this->info('AI retraining triggered successfully.');
        $this->line(json_encode($result['data'] ?? [], JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
