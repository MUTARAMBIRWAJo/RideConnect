<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;

class EnsureMobileUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $role = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
        if (! in_array(strtolower($role), ['driver', 'passenger'], true) && ! $user->mobile_user_id) {
            return response()->json(['message' => 'Mobile user access required.'], 403);
        }

        return $next($request);
    }
}
