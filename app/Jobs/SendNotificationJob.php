<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\Firebase\FCMManager;
use App\Traits\IdempotentJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, IdempotentJob;

    public int $tries = 5;
    public int $backoff = 5;

    public function __construct(
        public readonly string $recipientType,
        public readonly int $recipientId,
        public readonly string $fcmToken,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(FCMManager $fcmManager): void
    {
        if (!$this->startProcessing()) {
            Log::info("[SendNotificationJob] Duplicate notification event skipped for recipient {$this->recipientId}");
            return;
        }

        // Create pending log record
        $log = NotificationLog::create([
            'recipient_type' => $this->recipientType,
            'recipient_id' => $this->recipientId,
            'title' => $this->title,
            'body' => $this->body,
            'payload' => $this->data,
            'status' => 'pending',
        ]);

        try {
            $messageId = $fcmManager->sendToToken($this->fcmToken, $this->title, $this->body, $this->data);
            
            $log->update([
                'status' => 'sent',
                'message_id' => $messageId ?? 'success',
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
