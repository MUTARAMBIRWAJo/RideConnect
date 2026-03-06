<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\LedgerEntry;
use App\Models\User;

class LedgerPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ACCOUNTANT]);
    }

    public function view(User $user, LedgerEntry $entry): bool
    {
        return in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ACCOUNTANT]);
    }

    /** Ledger entries are immutable — never allow creation via policy gate. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, LedgerEntry $entry): bool
    {
        return false;
    }

    public function delete(User $user, LedgerEntry $entry): bool
    {
        return false;
    }
}
