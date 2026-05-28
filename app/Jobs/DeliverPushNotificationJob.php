<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\PushDeliveryBridge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly int $notificationId)
    {
        $this->onQueue('notifications');
    }

    public function handle(PushDeliveryBridge $pushDeliveryBridge): void
    {
        $notification = Notification::query()->find($this->notificationId);

        if (! $notification) {
            return;
        }

        $user = $notification->user()->first();

        if (! $user) {
            return;
        }

        $pushDeliveryBridge->deliverUserNotification($user, $notification);
    }
}
