<?php

namespace App\Jobs;

use App\Services\EventSourcing\EventPublisherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * ProcessOutboxJob — publishes pending event_outbox rows to the message bus.
 * Runs on the 'events' queue every minute (see console.php scheduler).
 */
class ProcessOutboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(public readonly int $batchSize = 100)
    {
        $this->onQueue('events');
    }

    public function handle(EventPublisherService $publisher): void
    {
        $publisher->publishPending($this->batchSize);
    }
}
