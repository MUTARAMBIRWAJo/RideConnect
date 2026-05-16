<?php

namespace App\Console\Commands;

use App\Services\RideAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RetrainAIModels extends Command
{
    protected $signature = 'ai:retrain-models {--model=* : Optional list of models to retrain}';

    protected $description = 'Trigger AI service retraining pipeline';

    public function handle(RideAIService $rideAIService): int
    {
        $models = array_values(array_filter((array) $this->option('model')));
        $modelName = empty($models) ? 'all' : implode(',', $models);
        $runId = $this->createTrainingRun($modelName);

        $result = $rideAIService->triggerRetrain([
            'models' => $models,
            'triggered_by' => 'laravel_scheduler',
            'requested_at' => now()->toIso8601String(),
        ]);

        if (! ($result['success'] ?? false)) {
            $this->finishTrainingRun($runId, 'failed', [
                'error' => $result['error'] ?? 'unknown error',
                'status' => $result['status'] ?? null,
            ]);
            $this->error('Failed to trigger retraining: '.($result['error'] ?? 'unknown error'));

            return self::FAILURE;
        }

        $this->finishTrainingRun($runId, 'triggered', $result['data'] ?? []);
        $this->info('AI retraining triggered successfully.');
        $this->line(json_encode($result['data'] ?? [], JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }

    private function createTrainingRun(string $modelName): ?int
    {
        if (! Schema::hasTable('ml_training_runs')) {
            return null;
        }

        return (int) DB::table('ml_training_runs')->insertGetId([
            'model_name' => $modelName,
            'status' => 'triggering',
            'started_at' => now(),
            'dataset_range' => json_encode(['source' => 'supabase', 'mode' => 'not_configured_yet'], JSON_THROW_ON_ERROR),
            'metrics_json' => null,
            'artifact_url' => null,
        ]);
    }

    private function finishTrainingRun(?int $runId, string $status, array $metadata): void
    {
        if ($runId === null || ! Schema::hasTable('ml_training_runs')) {
            return;
        }

        DB::table('ml_training_runs')
            ->where('id', $runId)
            ->update([
                'status' => $status,
                'ended_at' => now(),
                'metrics_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
            ]);
    }
}
