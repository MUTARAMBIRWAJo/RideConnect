<?php

namespace App\Policies;

use App\Models\PassengerRouteBoarding;
use App\Models\User;

class PassengerRouteBoardingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPassenger() || $user->isDriver() || $this->canManage($user);
    }

    public function view(User $user, PassengerRouteBoarding $boarding): bool
    {
        return $this->isOwner($user, $boarding) || $this->isAssignedDriver($user, $boarding) || $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $user->isPassenger() && $user->is_approved;
    }

    public function update(User $user, PassengerRouteBoarding $boarding): bool
    {
        return $this->isAssignedDriver($user, $boarding) || $this->canManage($user);
    }

    private function isOwner(User $user, PassengerRouteBoarding $boarding): bool
    {
        return (int) $user->mobile_user_id === (int) $boarding->passenger_id;
    }

    private function isAssignedDriver(User $user, PassengerRouteBoarding $boarding): bool
    {
        return $user->driver?->id && (int) $boarding->busRouteAssignment?->driver_id === (int) $user->driver->id;
    }

    private function canManage(User $user): bool
    {
        return (bool) ($user->role?->isSuperAdmin() || $user->role?->isManager() || strtoupper((string) $user->role?->value) === 'OFFICER');
    }
}
