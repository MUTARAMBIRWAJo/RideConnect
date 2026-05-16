<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ml_predictions')) {
            Schema::create('ml_predictions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
                $table->string('model_name', 120);
                $table->string('model_version', 120)->nullable();
                $table->string('endpoint', 180)->nullable();
                $table->jsonb('input_payload');
                $table->jsonb('output_payload')->nullable();
                $table->unsignedInteger('latency_ms')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['model_name', 'created_at']);
                $table->index(['trip_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('ml_model_versions')) {
            Schema::create('ml_model_versions', function (Blueprint $table): void {
                $table->id();
                $table->string('model_name', 120);
                $table->string('version', 120);
                $table->jsonb('metrics_json')->nullable();
                $table->boolean('is_active')->default(false);
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['model_name', 'version']);
                $table->index(['model_name', 'is_active']);
            });
        }

        if (! Schema::hasTable('ml_training_runs')) {
            Schema::create('ml_training_runs', function (Blueprint $table): void {
                $table->id();
                $table->string('model_name', 120);
                $table->string('status', 40);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->jsonb('dataset_range')->nullable();
                $table->jsonb('metrics_json')->nullable();
                $table->string('artifact_url', 2048)->nullable();

                $table->index(['model_name', 'status']);
                $table->index('started_at');
            });
        }

        foreach ([
            ['model_name' => 'DriverRanker', 'version' => 'v1', 'metrics_json' => ['source' => 'deployed_health']],
            ['model_name' => 'DemandHeuristicModelV1', 'version' => 'v1', 'metrics_json' => ['source' => 'deployed_contract']],
            ['model_name' => 'BehaviorAnomalyDetector', 'version' => 'behavior_isolation_forest_v1', 'metrics_json' => ['source' => 'deployed_health']],
        ] as $version) {
            DB::table('ml_model_versions')->updateOrInsert(
                [
                    'model_name' => $version['model_name'],
                    'version' => $version['version'],
                ],
                [
                    'metrics_json' => json_encode($version['metrics_json'], JSON_THROW_ON_ERROR),
                    'is_active' => true,
                    'created_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_training_runs');
        Schema::dropIfExists('ml_model_versions');
        Schema::dropIfExists('ml_predictions');
    }
};
