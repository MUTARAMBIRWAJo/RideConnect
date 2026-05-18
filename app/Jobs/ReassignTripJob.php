<?php

namespace App\Jobs;

use App\Services\ReassignmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReassignTripJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $tripId,
        public readonly string $reason = 'reassignment_requested',
        public readonly string $triggeredBy = 'job',
    ) {}

    public function handle(ReassignmentService $reassignmentService): void
    {
        $reassignmentService->reassign($this->tripId, $this->reason, $this->triggeredBy);
    }
}
