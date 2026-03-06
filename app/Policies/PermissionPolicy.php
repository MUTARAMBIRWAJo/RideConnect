<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Spatie\Permission\Models\Permission;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function view(User $user, Permission $permission): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function update(User $user, Permission $permission): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $this->isSuperAdmin($user);
    }

    private function isSuperAdmin(User $user): bool
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('Super_admin')) {
            return true;
        }

        return ($user->role?->value ?? $user->role) === UserRole::SUPER_ADMIN->value;
    }
}
