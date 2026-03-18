<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use PDOException;

class SafeEloquentUserProvider extends EloquentUserProvider
{
    /**
     * Indicates whether the latest credential lookup failed due to DB error.
     */
    protected bool $credentialsLookupDbFailure = false;

    /**
     * Retrieve a user by their unique identifier.
     * Return null on database errors instead of throwing.
     *
     * @param  mixed  $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        try {
            return parent::retrieveById($identifier);
        } catch (QueryException|PDOException $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Retrieve a user by the given token and identifier.
     * Safely return null on DB errors.
     */
    public function retrieveByToken($identifier, $token)
    {
        try {
            return parent::retrieveByToken($identifier, $token);
        } catch (QueryException|PDOException $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Retrieve a user by the given credentials.
     * This method is used by Auth::attempt(), so it must be safe against
     * transient DB outages (timeouts, network errors).
     *
     * @param  array  $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $this->credentialsLookupDbFailure = false;

        try {
            return parent::retrieveByCredentials($credentials);
        } catch (QueryException|PDOException $e) {
            $this->credentialsLookupDbFailure = true;
            return null;
        } catch (\Exception $e) {
            $this->credentialsLookupDbFailure = true;
            return null;
        }
    }

    /**
     * Consume and clear DB-failure flag set during credential lookup.
     */
    public function consumeCredentialsLookupDbFailure(): bool
    {
        $failed = $this->credentialsLookupDbFailure;
        $this->credentialsLookupDbFailure = false;

        return $failed;
    }
}
