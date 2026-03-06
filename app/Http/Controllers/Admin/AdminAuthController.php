<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    /**
     * Show the admin login form.
     */
    public function showLogin(): View
    {
        return view('auth.admin-login');
    }

    /**
     * Handle admin login.
     * 
     * Only admin, officer, and superadmin roles are allowed.
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials = $request->only('email', 'password');

        // Attempt to authenticate with admin guard
        if (Auth::guard('admin')->attempt($credentials, $request->remember)) {
            $request->session()->regenerate();
            
            // Check user role after authentication
            $user = Auth::guard('admin')->user();
            
            // Verify the user has a manager role
            if (!$user->role || !$user->role->isManager()) {
                Auth::guard('admin')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                
                throw ValidationException::withMessages([
                    'email' => ['Unauthorized: Only admin, officer, and superadmin roles can access the backend.'],
                ]);
            }

            return redirect()->intended(route('admin.dashboard'))->with('success', 'Welcome back, ' . $user->name . '!');
        }

        throw ValidationException::withMessages([
            'email' => ['The provided credentials do not match our records.'],
        ]);
    }

    /**
     * Handle admin logout.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.login')->with('success', 'You have been logged out.');
    }

    /**
     * Show the admin registration form (for superadmin to create managers).
     */
    public function showRegister(): View
    {
        // Only superadmin can access this
        return view('auth.admin-register');
    }

    /**
     * Handle admin registration (for superadmin to create managers).
     */
    public function register(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Verify the current user is a superadmin
        $currentUser = Auth::guard('admin')->user();
        if (!$currentUser || !$currentUser->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'email' => ['Only superadmin can create manager accounts.'],
            ]);
        }

        // Default role is OFFICER if not specified
        $role = $request->input('role', 'officer');
        
        // Validate role
        $validRoles = array_map(fn($role) => $role->value, UserRole::managerRoles());
        if (!in_array(strtoupper($role), $validRoles)) {
            $role = 'OFFICER';
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => UserRole::from(strtoupper($role)),
            'is_verified' => true,
            'is_approved' => true,
            'approved_by' => $currentUser->id,
            'approved_at' => now(),
        ]);

        return redirect()->route('admin.dashboard')->with('success', 'Manager account created successfully.');
    }
}
