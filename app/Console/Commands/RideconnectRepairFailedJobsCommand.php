<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RideconnectRepairFailedJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rideconnect:repair-failed-jobs 
                            {--analyze : Analyze failed jobs without taking action}
                            {--retry : Retry safe failed jobs}
                            {--archive : Archive unrecoverable jobs}
                            {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze and repair failed jobs in the queue';

    public function handle(): int
    {
        $this->info('RideConnect Failed Jobs Repair');
        $this->info('================================');
        $this->newLine();

        if (!DB::getSchemaBuilder()->hasTable('failed_jobs')) {
            $this->warn('Failed jobs table does not exist');
            return self::SUCCESS;
        }

        // Get all failed jobs
        $failedJobs = DB::table('failed_jobs')->get();
        $totalJobs = $failedJobs->count();

        if ($totalJobs === 0) {
            $this->info('✓ No failed jobs found');
            return self::SUCCESS;
        }

        $this->info("Found {$totalJobs} failed jobs");
        $this->newLine();

        // Categorize failures
        $categories = $this->categorizeFailures($failedJobs);
        $this->displayCategories($categories);

        if ($this->option('analyze')) {
            $this->newLine();
            $this->info('Analysis complete. Use --retry or --archive to take action.');
            return self::SUCCESS;
        }

        if ($this->option('retry')) {
            $this->retrySafeJobs($categories, $this->option('dry-run'));
        }

        if ($this->option('archive')) {
            $this->archiveUnrecoverableJobs($categories, $this->option('dry-run'));
        }

        $this->newLine();
        $this->info('Repair complete');
        return self::SUCCESS;
    }

    private function categorizeFailures($failedJobs): array
    {
        $categories = [
            'firebase' => [],
            'notifications' => [],
            'payments' => [],
            'sync_jobs' => [],
            'queue_issues' => [],
            'other' => [],
        ];

        foreach ($failedJobs as $job) {
            $payload = json_decode($job->payload, true);
            $exception = $job->exception;

            if (str_contains($exception, 'Firebase') || str_contains($exception, 'Firestore')) {
                $categories['firebase'][] = $job;
            } elseif (str_contains($exception, 'Notification') || str_contains($exception, 'FCM')) {
                $categories['notifications'][] = $job;
            } elseif (str_contains($exception, 'Payment') || str_contains($exception, 'Stripe')) {
                $categories['payments'][] = $job;
            } elseif (str_contains($exception, 'Sync') || str_contains($exception, 'DriverLocationSyncJob')) {
                $categories['sync_jobs'][] = $job;
            } elseif (str_contains($exception, 'Queue') || str_contains($exception, 'Connection')) {
                $categories['queue_issues'][] = $job;
            } else {
                $categories['other'][] = $job;
            }
        }

        return $categories;
    }

    private function displayCategories(array $categories): void
    {
        foreach ($categories as $category => $jobs) {
            if (!empty($jobs)) {
                $this->info("{$category}: " . count($jobs) . ' jobs');
                
                // Show sample exceptions
                if (count($jobs) > 0 && count($jobs) <= 3) {
                    foreach ($jobs as $job) {
                        $exception = substr($job->exception, 0, 100);
                        $this->line("  - {$exception}...");
                    }
                } elseif (count($jobs) > 3) {
                    $exception = substr($jobs[0]->exception, 0, 100);
                    $this->line("  - {$exception}...");
                    $this->line("  - ... and " . (count($jobs) - 1) . " more");
                }
            }
        }
    }

    private function retrySafeJobs(array $categories, bool $dryRun): void
    {
        $this->newLine();
        $this->info('Retrying safe jobs...');

        $safeCategories = ['sync_jobs', 'queue_issues'];
        $totalRetried = 0;

        foreach ($safeCategories as $category) {
            $jobs = $categories[$category] ?? [];

            foreach ($jobs as $job) {
                if ($dryRun) {
                    $this->line("Would retry job ID: {$job->id} ({$category})");
                } else {
                    try {
                        // Move job back to queue
                        $payload = json_decode($job->payload, true);

                        // Safe check for command structure
                        $command = $payload['command'] ?? null;
                        if (!$command || !isset($command['name'])) {
                            Log::warning('Invalid job payload, skipping retry', [
                                'job_id' => $job->id,
                                'payload' => $payload,
                            ]);
                            $this->warn("Skipping invalid job ID: {$job->id} (no command name)");
                            continue;
                        }

                        // Delete from failed_jobs
                        DB::table('failed_jobs')->where('id', $job->id)->delete();

                        // Re-queue the job
                        $queue = $payload['queue'] ?? 'default';
                        $jobClass = $command['name'];

                        if (class_exists($jobClass)) {
                            dispatch(new $jobClass())->onQueue($queue);
                            $totalRetried++;
                            $this->line("Retried job ID: {$job->id}");
                        } else {
                            Log::warning('Job class not found, skipping retry', [
                                'job_id' => $job->id,
                                'job_class' => $jobClass,
                            ]);
                            $this->warn("Skipping job ID: {$job->id} (class {$jobClass} not found)");
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to retry job', [
                            'job_id' => $job->id,
                            'error' => $e->getMessage(),
                        ]);
                        $this->error("Failed to retry job ID: {$job->id} - {$e->getMessage()}");
                    }
                }
            }
        }

        if ($dryRun) {
            $this->info("Would retry {$totalRetried} jobs");
        } else {
            $this->info("Retried {$totalRetried} jobs");
        }
    }

    private function archiveUnrecoverableJobs(array $categories, bool $dryRun): void
    {
        $this->newLine();
        $this->info('Archiving unrecoverable jobs...');

        $unrecoverableCategories = ['firebase', 'notifications', 'payments'];
        $totalArchived = 0;

        foreach ($unrecoverableCategories as $category) {
            $jobs = $categories[$category] ?? [];
            
            foreach ($jobs as $job) {
                if ($dryRun) {
                    $this->line("Would archive job ID: {$job->id} ({$category})");
                } else {
                    try {
                        // Archive to failed_jobs_archive table if it exists
                        if (DB::getSchemaBuilder()->hasTable('failed_jobs_archive')) {
                            DB::table('failed_jobs_archive')->insert((array) $job);
                        }
                        
                        // Delete from failed_jobs
                        DB::table('failed_jobs')->where('id', $job->id)->delete();
                        
                        $totalArchived++;
                        $this->line("Archived job ID: {$job->id}");
                    } catch (\Exception $e) {
                        $this->error("Failed to archive job ID: {$job->id} - {$e->getMessage()}");
                    }
                }
            }
        }

        if ($dryRun) {
            $this->info("Would archive {$totalArchived} jobs");
        } else {
            $this->info("Archived {$totalArchived} jobs");
        }
    }
}
