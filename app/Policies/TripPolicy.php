<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->value ?? $user->role, ['SUPER_ADMIN', 'ADMIN'], true);
    }

    public function view(User $user, Trip $trip): bool
    {
        return $this->viewAny($user);
    }
}
