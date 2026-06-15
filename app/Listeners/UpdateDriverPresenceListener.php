<?php

namespace App\Listeners;

use App\Events\DriverOnline;
use App\Events\DriverOffline;
use App\Jobs\UpdateDriverPresenceJob;

class UpdateDriverPresenceListener
{
    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        if ($event instanceof DriverOnline) {
            dispatch(new UpdateDriverPresenceJob($event->driverId, $event->status));
        } elseif ($event instanceof DriverOffline) {
            dispatch(new UpdateDriverPresenceJob($event->driverId, 'offline'));
        }
    }
}
