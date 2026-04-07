<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOfficerRole
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return redirect()->route('filament.auth.login');
        }

        $user = auth()->user();
        
        // Check if user has officer role via Spatie Permissions
        if (!$user->hasRole('officer') && !$user->hasRole('OFFICER')) {
            abort(403, 'Only Officers can access this panel.');
        }

        return $next($request);
    }
}
