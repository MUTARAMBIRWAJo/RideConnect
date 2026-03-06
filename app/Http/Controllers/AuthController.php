<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Show the registration form.
     */
    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Handle user registration.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:passenger,driver'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => UserRole::from($request->role),
            'is_approved' => true, // Auto-approve for testing
        ]);

        // Don't log the user in - redirect to login
        return redirect()->route('auth.login')->with('info', 'Registration successful! You can now login.');
    }

    /**
     * Show the login form.
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Handle user login.
     * Redirects based on user role:
     * - admin, officer, superadmin, accountant -> /admin (Filament)
     * - driver, passenger -> /dashboard
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials = $request->only('email', 'password');

        // Try to authenticate
        if (!Auth::attempt($credentials, $request->filled('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $request->session()->regenerate();
        
        $user = Auth::user();
        
        // Debug: Log user role
        \Log::info('User logged in:', [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role ? $user->role->value : 'null',
            'is_approved' => $user->is_approved,
            'isManager' => $user->isManager(),
        ]);
        
        // Check if user is approved (skip for manager roles)
        $isManagerRole = $user->role && (
            $user->role === UserRole::ADMIN || 
            $user->role === UserRole::OFFICER || 
            $user->role === UserRole::SUPER_ADMIN ||
            $user->role === UserRole::ACCOUNTANT
        );
        
        if (!$user->is_approved && !$isManagerRole) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('auth.login')->with('error', 'Your account is pending approval. Please contact administrator.');
        }
        
        // Role-based redirect
        if ($isManagerRole) {
            $panelPath = '/'.trim(Filament::getPanel('admin')->getPath(), '/');

            $redirectPath = match ($user->role?->value) {
                UserRole::SUPER_ADMIN->value => "{$panelPath}/super-dashboard",
                UserRole::ADMIN->value => "{$panelPath}/admin-dashboard",
                UserRole::ACCOUNTANT->value => "{$panelPath}/accountant-dashboard",
                UserRole::OFFICER->value => "{$panelPath}/officer-dashboard",
                default => "{$panelPath}",
            };

            return redirect()->to($redirectPath)->with('success', 'Welcome back, ' . $user->name . '!');
        }
        
        // Regular user (driver, passenger) redirect to dashboard
        return redirect()->to('/dashboard')->with('success', 'Welcome back!');
    }

    /**
     * Handle user logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.login')->with('success', 'You have been logged out.');
    }
}
