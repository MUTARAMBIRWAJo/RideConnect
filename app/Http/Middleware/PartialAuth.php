<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PartialAuth
{
    /**
     * Handle middleware for partially authenticated users (in 2FA flow)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = session('pending_auth_user_id');

        if (!$userId) {
            return redirect()->route('auth.login');
        }

        return $next($request);
    }
}
