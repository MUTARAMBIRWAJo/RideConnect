<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class HeartbeatMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Process heartbeat after response to avoid blocking the user
        $user = $request->user();
        if ($user) {
            // Update user table
            $user->update([
                'last_seen_at' => now(),
                'is_online' => true,
            ]);

            // Update drivers table if user is a driver
            if ($user->role && $user->role->value === 'DRIVER' && Schema::hasTable('drivers')) {
                DB::table('drivers')
                    ->where('user_id', $user->id)
                    ->update([
                        'last_seen_at' => now(),
                        'is_online' => true,
                    ]);
            }
        }

        return $response;
    }
}
