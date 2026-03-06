<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use PDOException;

class SafeEloquentUserProvider extends EloquentUserProvider
{
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
}
