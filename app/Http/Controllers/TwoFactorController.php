<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    protected Google2FA $google2fa;

    public function __construct(Google2FA $google2fa)
    {
        $this->google2fa = $google2fa;
    }

    /**
     * Show the 2FA challenge form
     */
    public function show()
    {
        $userId = session('pending_auth_user_id');

        if (! $userId) {
            return redirect()->route('auth.login')
                ->with('error', 'Invalid request. Please login again.');
        }

        return view('auth.two-factor-challenge', [
            'user' => User::find($userId),
        ]);
    }

    /**
     * Verify the 2FA code
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $userId = session('pending_auth_user_id');

        if (! $userId) {
            return redirect()->route('auth.login')
                ->with('error', 'Invalid request. Please login again.');
        }

        $user = User::find($userId);

        if (! $user || ! $user->hasMfaEnabled() || ! $user->hasMfaConfirmed()) {
            return redirect()->route('auth.login')
                ->with('error', 'Invalid 2FA configuration.');
        }

        // Check if account is locked
        if ($user->isMfaLocked()) {
            return back()->with('error', 'Too many failed attempts. Please try again in 10 minutes.');
        }

        // Verify the code
        $verified = $this->google2fa->verifyKey(
            $user->two_factor_secret,
            $request->code,
            2 // Allow 2 time windows (±30 seconds)
        );

        if (! $verified) {
            $user->incrementMfaAttempts();

            $remaining = 5 - $user->mfa_attempts;
            $message = $remaining > 0
                ? "Invalid code. {$remaining} attempts remaining."
                : 'Too many failed attempts. Please try again in 10 minutes.';

            return back()->with('error', $message);
        }

        // Code verified, log user in
        Auth::login($user, remember: (bool) $request->filled('remember'));

        $user->resetMfaAttempts();
        $this->recordLoginActivity($user);
        session()->forget('pending_auth_user_id');

        \Log::info('2FA verification successful', ['user_id' => $user->id]);

        return $this->redirectAfterLogin($user);
    }

    /**
     * Verify backup code instead of TOTP
     */
    public function verifyBackupCode(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $userId = session('pending_auth_user_id');

        if (! $userId) {
            return redirect()->route('auth.login')
                ->with('error', 'Invalid request. Please login again.');
        }

        $user = User::find($userId);

        if (! $user || ! $user->two_factor_backup_codes) {
            return redirect()->route('auth.login')
                ->with('error', 'Invalid 2FA configuration.');
        }

        $codes = $user->two_factor_backup_codes;
        $code = $request->code;

        if (! in_array($code, $codes)) {
            $user->incrementMfaAttempts();

            return back()->with('error', 'Invalid backup code.');
        }

        // Remove used code
        $user->two_factor_backup_codes = array_values(
            array_filter($codes, fn ($c) => $c !== $code)
        );
        $user->save();

        // Log user in
        Auth::login($user, remember: true);
        $user->resetMfaAttempts();
        $this->recordLoginActivity($user);
        session()->forget('pending_auth_user_id');

        \Log::info('2FA verified via backup code', ['user_id' => $user->id]);

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
    }

    /**
     * Redirect based on role
     */
    private function redirectAfterLogin(User $user)
    {
        if ($user->isManager()) {
            $panelPath = '/'.trim(\Filament\Facades\Filament::getPanel('admin')->getPath(), '/');

            return redirect()->to(match ($user->role?->value) {
                \App\Enums\UserRole::SUPER_ADMIN->value => "{$panelPath}/super-dashboard",
                \App\Enums\UserRole::ADMIN->value => "{$panelPath}/admin-dashboard",
                \App\Enums\UserRole::ACCOUNTANT->value => '/'.trim(\Filament\Facades\Filament::getPanel('accountant')->getPath(), '/'),
                \App\Enums\UserRole::OFFICER->value => '/'.trim(\Filament\Facades\Filament::getPanel('officer')->getPath(), '/'),
                default => "{$panelPath}",
            })->with('success', '2FA verified. Welcome!');
        }

        return redirect()->to('/dashboard')->with('success', 'Welcome back!');
    }
}
