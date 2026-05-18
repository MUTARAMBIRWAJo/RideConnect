<?php

namespace App\Policies;

use App\Models\CorridorStop;
use App\Models\User;

class CorridorStopPolicy
{
    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return (bool) ($user->role?->isSuperAdmin() || $user->role?->isManager() || strtoupper((string) $user->role?->value) === 'OFFICER');
    }
}
