<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('driver.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('passenger.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('trip.v3.{id}', function ($user, $id) {
    $trip = \App\Models\V3\TripV3::query()->find($id);

    if (! $trip) {
        return false;
    }

    if ((int) $trip->user_id === (int) $user->id) {
        return true;
    }

    $driverId = $user->driver?->id ?? null;

    return $driverId && in_array((int) $driverId, array_filter([
        $trip->driver_id,
        $trip->matched_driver_id,
    ]), true);
});
