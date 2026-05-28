<?php

namespace App\Policies;

use App\Models\Ride;
use App\Models\User;

class RidePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->value ?? $user->role, ['SUPER_ADMIN', 'ADMIN'], true);
    }

    public function view(User $user, Ride $ride): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->value ?? $user->role, ['SUPER_ADMIN', 'ADMIN'], true);
    }

    public function createAdminRide(User $user): bool
    {
        return $this->create($user);
    }

    public function update(User $user, Ride $ride): bool
    {
        if (in_array($user->role?->value ?? $user->role, ['SUPER_ADMIN', 'ADMIN'], true)) {
            return true;
        }

        return $user->role?->value === 'DRIVER'
            && $ride->driver?->user_id === $user->id;
    }

    public function delete(User $user, Ride $ride): bool
    {
        return in_array($user->role?->value ?? $user->role, ['SUPER_ADMIN', 'ADMIN'], true);
    }
}
