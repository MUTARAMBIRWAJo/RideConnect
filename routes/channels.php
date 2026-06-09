<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user (via sanctum token) can
| listen to the named channel.
|
| Channel naming must stay in sync with config/realtime.php 'channels'
| because Flutter's channel builder reads that same config.
|
*/

// Private per-trip channel — passenger and the assigned driver.
Broadcast::channel('trip.{tripId}', function ($user, $tripId) {
    return Gate::allows('view-trip', [$user, (int) $tripId]);
});

// Private per-driver channel — the driver only.
Broadcast::channel('driver.{driverId}', function ($user, $driverId) {
    return Gate::allows('view-driver-channel', [$user, (int) $driverId]);
});

// Private per-user channel — the passenger only.
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return Gate::allows('view-user-channel', [$user, (int) $userId]);
});
