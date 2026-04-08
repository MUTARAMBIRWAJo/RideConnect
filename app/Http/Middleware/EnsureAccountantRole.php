<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
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

        // Accept either enum-backed role or Spatie role assignment.
        $hasAccountantAccess = ($user->role === UserRole::ACCOUNTANT)
            || $user->hasAnyRole(['Accountant', 'accountant', 'ACCOUNTANT']);

        if (! $hasAccountantAccess) {
            abort(403, 'Only Accountants can access this panel.');
        }

        return $next($request);
    }
}
