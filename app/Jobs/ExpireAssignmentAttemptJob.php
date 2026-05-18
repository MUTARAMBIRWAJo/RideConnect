<?php

namespace App\Jobs;

use App\Models\TripAssignmentAttempt;
use App\Services\ReassignmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ExpireAssignmentAttemptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $attemptId) {}

    public function handle(ReassignmentService $reassignmentService): void
    {
        $tripId = DB::transaction(function (): ?int {
            $attempt = TripAssignmentAttempt::query()->lockForUpdate()->find($this->attemptId);
            if (! $attempt || ! in_array($attempt->status, [TripAssignmentAttempt::STATUS_PENDING, TripAssignmentAttempt::STATUS_NOTIFIED], true)) {
                return null;
            }

            if ($attempt->expires_at && $attempt->expires_at->isFuture()) {
                return null;
            }

            $attempt->update([
                'status' => TripAssignmentAttempt::STATUS_EXPIRED,
                'responded_at' => now(),
            ]);

            return (int) $attempt->trip_id;
        });

        if ($tripId) {
            $reassignmentService->reassign($tripId, 'assignment_timeout', 'job');
        }
    }
}
