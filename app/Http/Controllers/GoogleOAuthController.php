<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Filament\Facades\Filament;

class GoogleOAuthController extends Controller
{
    /**
     * Redirect to Google OAuth provider
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google OAuth callback
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            \Log::error('Google OAuth error: ' . $e->getMessage());
            return redirect()->route('auth.login')
                ->with('error', 'Failed to authenticate with Google. Please try again.');
        }

        // Find or create user
        $user = User::where('email', $googleUser->getEmail())->first();

        if (!$user) {
            // Create new user
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'password' => Hash::make(Str::random(32)), // Random password (not used)
                'role' => UserRole::ADMIN, // Default role for OAuth (can be changed)
                'is_approved' => false, // Require approval for new OAuth users
                'is_verified' => true, // Mark as verified (email from Google)
            ]);

            event(new Registered($user));

            return redirect()->route('auth.login')
                ->with('info', 'Account created. Please contact administrator for approval.');
        } else {
            // Update existing user with Google ID
            if (!$user->google_id) {
                $user->update(['google_id' => $googleUser->getId()]);
            }

            // Check if approved
            if (!$user->is_approved && !$user->isManager()) {
                return redirect()->route('auth.login')
                    ->with('error', 'Your account is pending approval. Please contact administrator.');
            }
        }

        // Store pending user in session (for MFA check)
        session(['pending_auth_user_id' => $user->id]);

        // Check if MFA is enabled
        if ($user->hasMfaEnabled() && $user->hasMfaConfirmed()) {
            return redirect()->route('auth.two-factor-challenge');
        }

        // MFA not enabled, log user in directly
        Auth::login($user, remember: true);
        $this->recordLoginActivity($user);
        session()->forget('pending_auth_user_id');

        return $this->redirectAfterLogin($user);
    }

    /**
     * Record login activity
     */
    private function recordLoginActivity(User $user): void
    {
        $user->update([
            'last_login_ip' => request()->ip(),
            'last_login_user_agent' => request()->userAgent(),
            'last_login_at' => now(),
        ]);

        \Log::info('User logged in via Google OAuth', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => request()->ip(),
        ]);
    }

    /**
     * Redirect user based on role
     */
    private function redirectAfterLogin(User $user): RedirectResponse
    {
        if ($user->isManager()) {
            $panelPath = '/' . trim(Filament::getPanel('admin')->getPath(), '/');
            
            return redirect()->to(match ($user->role?->value) {
                UserRole::SUPER_ADMIN->value => "{$panelPath}/super-dashboard",
                UserRole::ADMIN->value => "{$panelPath}/admin-dashboard",
                UserRole::ACCOUNTANT->value => '/' . trim(Filament::getPanel('accountant')->getPath(), '/'),
                UserRole::OFFICER->value => '/' . trim(Filament::getPanel('officer')->getPath(), '/'),
                default => "{$panelPath}",
            })->with('success', 'Welcome back, ' . $user->name . '!');
        }

        return redirect()->to('/dashboard')->with('success', 'Welcome back!');
    }
}
