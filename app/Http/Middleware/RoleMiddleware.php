<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Check if user has a role
        if (!$user->role) {
            return response()->json([
                'success' => false,
                'message' => 'User role not found'
            ], 403);
        }

        // Normalize roles (handle both 'super_admin' and 'SUPER_ADMIN' formats)
        $normalizedRoles = array_map(function ($role) {
            return strtoupper($role);
        }, $roles);

        // Get user's role value (normalize to uppercase)
        $userRoleValue = strtoupper($user->role->value ?? '');

        // Check if user's role is in the allowed roles
        if (!in_array($userRoleValue, $normalizedRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource',
                'debug' => [
                    'user_role' => $userRoleValue,
                    'required_roles' => $normalizedRoles
                ]
            ], 403);
        }

        return $next($request);
    }

    /**
     * Check if user has any of the specified roles
     */
    public function hasAnyRole($user, array $roles): bool
    {
        if (!$user || !$user->role) {
            return false;
        }

        $userRoleValue = strtoupper($user->role->value ?? '');
        $normalizedRoles = array_map('strtoupper', $roles);

        return in_array($userRoleValue, $normalizedRoles);
    }

    /**
     * Check if user has all of the specified roles
     */
    public function hasAllRoles($user, array $roles): bool
    {
        if (!$user || !$user->role) {
            return false;
        }

        $userRoleValue = strtoupper($user->role->value ?? '');
        $normalizedRoles = array_map('strtoupper', $roles);

        return in_array($userRoleValue, $normalizedRoles);
    }
}
