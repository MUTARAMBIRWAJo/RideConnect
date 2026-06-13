<?php

namespace App\Http\Concerns;

use App\Models\User;
use App\Services\Identity\IdentityResolverService;

trait ResolvesCanonicalIdentity
{
    protected function identityResolver(): IdentityResolverService
    {
        return app(IdentityResolverService::class);
    }

    /**
     * Canonical passenger owner id for writes (users.id).
     */
    protected function passengerOwnerId(User $user): int
    {
        return $this->identityResolver()->passengerOwnerId($user);
    }

    /**
     * @return list<int>
     */
    protected function passengerOwnerIdsForQuery(User $user): array
    {
        return $this->identityResolver()->passengerOwnerIdsForQuery($user);
    }

    protected function userOwnsPassengerReference(User $user, int $passengerReference): bool
    {
        return $this->identityResolver()->userOwnsPassengerReference($user, $passengerReference);
    }

    /**
     * @deprecated Use passengerOwnerId() for new records.
     */
    protected function resolvePassengerMobileUserId(User $user): int
    {
        return $this->passengerOwnerId($user);
    }

    /**
     * Ensures legacy mobile_users link exists; returns canonical users.id for domain writes.
     */
    protected function resolveOrCreatePassengerOwnerId(User $user): int
    {
        $this->identityResolver()->ensureLegacyMobileUserLink($user);

        return $this->passengerOwnerId($user);
    }
}
