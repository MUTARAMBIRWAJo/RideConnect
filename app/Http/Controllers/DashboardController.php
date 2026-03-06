<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Handle dashboard redirect based on user role.
     * 
     * For web users (SuperAdmin, Admin, Accountant, Officer): Show web dashboard
     * For mobile users (Driver, Passenger): Return JSON redirect to mobile app
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Check if user is approved
        if (!$user->is_approved) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('auth.login')->with('error', 'Your account is pending approval. Please contact administrator.');
        }

        // For mobile app users (Driver/Passenger), return JSON response with redirect info
        if ($user->isDriver() || $user->isPassenger()) {
            return response()->json([
                'success' => true,
                'message' => 'Login successful. Use the mobile app for dashboard.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role->value,
                    ],
                    'redirect_to' => 'mobile_app',
                    'mobile_app_deep_link' => 'rideconnect://dashboard',
                ],
            ]);
        }
        
        // For web users (SuperAdmin, Manager roles)
        if ($user->role->isSuperAdmin()) {
            return view('dashboards.super-admin');
        }
        
        if ($user->role->isManager()) {
            return view('dashboards.manager');
        }
        
        // Default fallback - redirect to mobile app
        return response()->json([
            'success' => true,
            'message' => 'Login successful. Use the mobile app for dashboard.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                ],
                'redirect_to' => 'mobile_app',
            ],
        ]);
    }
}
