<?php

namespace App\Policies;

use App\Models\User;

class TransportCorridorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPassenger() || $user->isDriver() || $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return (bool) ($user->role?->isSuperAdmin() || $user->role?->isManager() || strtoupper((string) $user->role?->value) === 'OFFICER');
    }
}
