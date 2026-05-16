<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;

class EnsureOfficerRole
{
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check()) {
            return redirect()->route('filament.auth.login');
        }

        $user = auth()->user();

        // Accept either enum-backed role or Spatie role assignment.
        $hasOfficerAccess = ($user->role === UserRole::OFFICER)
            || $user->hasAnyRole(['Officer', 'officer', 'OFFICER']);

        if (! $hasOfficerAccess) {
            abort(403, 'Only Officers can access this panel.');
        }

        return $next($request);
    }
}
