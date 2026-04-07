<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAccountantRole
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return redirect()->route('filament.auth.login');
        }

        $user = auth()->user();
        
        // Check if user has accountant role via Spatie Permissions
        if (!$user->hasRole('accountant') && !$user->hasRole('ACCOUNTANT')) {
            abort(403, 'Only Accountants can access this panel.');
        }

        return $next($request);
    }
}
