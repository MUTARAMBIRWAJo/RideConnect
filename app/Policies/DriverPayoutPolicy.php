<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DriverPayout;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DriverPayoutPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return ($user->role?->value ?? $user->role) === UserRole::ACCOUNTANT->value;
    }

    public function view(User $user, DriverPayout $driverPayout): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, DriverPayout $driverPayout): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, DriverPayout $driverPayout): bool
    {
        return false;
    }

    public function process(User $user): bool
    {
        return $this->viewAny($user);
    }
}
