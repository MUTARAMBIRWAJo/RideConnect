<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use PragmaRX\Google2FAQRCode\Google2FA as Google2FAQRCode;

class MfaSetupController extends Controller
{
    protected Google2FA $google2fa;

    protected Google2FAQRCode $google2faQr;

    public function __construct(Google2FA $google2fa, Google2FAQRCode $google2faQr)
    {
        $this->google2fa = $google2fa;
        $this->google2faQr = $google2faQr;
    }

    /**
     * Check if user can manage MFA (for disable operations)
     */
    protected function canManageMfa(): bool
    {
        $user = auth()->user();

        return $user && ($user->isSuperAdmin() || $user->role?->value === \App\Enums\UserRole::ADMIN->value);
    }

    /**
     * Show MFA settings page
     */
    public function settings()
    {
        $user = auth()->user();

        return view('settings.mfa', [
            'user' => $user,
            'canDisable' => $this->canManageMfa(),
        ]);
    }

    /**
     * Show MFA setup page
     */
    public function show()
    {
        $user = auth()->user();

        // Generate secret if not already created
        if (! session('mfa_secret')) {
            $secret = $this->google2fa->generateSecretKey();
            session(['mfa_secret' => $secret]);
        } else {
            $secret = session('mfa_secret');
        }

        // Generate QR code
        $qrCode = $this->google2faQr->getQRCodeInline(
            config('app.name'),
            $user->email,
            $secret
        );

        return view('auth.mfa-setup', [
            'qrCode' => $qrCode,
            'secret' => $secret,
            'user' => $user,
        ]);
    }

    /**
     * Store MFA setup (verify code first)
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $user = auth()->user();
        $secret = session('mfa_secret');

        if (! $secret) {
            return back()->with('error', 'MFA setup session expired. Please try again.');
        }

        // Verify the code
        $verified = $this->google2fa->verifyKey($secret, $request->code, 2);

        if (! $verified) {
            return back()->with('error', 'Invalid verification code. Please try again.');
        }

        // Generate backup codes
        $backupCodes = collect(range(1, 10))
            ->map(fn () => Str::random(8))
            ->toArray();

        // Save to user
        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_backup_codes' => $backupCodes,
            'two_factor_enabled' => true,
        ]);

        session()->forget('mfa_secret');

        \Log::info('MFA enabled', ['user_id' => $user->id]);

        return redirect('/dashboard')
            ->with('success', 'Two-Factor Authentication enabled successfully!')
            ->with('backup_codes', $backupCodes);
    }

    /**
     * Show backup codes
     */
    public function backupCodes()
    {
        $user = auth()->user();

        if (! $user->hasMfaEnabled()) {
            return redirect()->route('mfa.settings')
                ->with('error', 'MFA is not enabled on this account.');
        }

        return view('settings.backup-codes', [
            'user' => $user,
        ]);
    }

    /**
     * Disable MFA
     */
    public function disable(Request $request)
    {
        // Only SuperAdmin/Admin can disable MFA
        if (! $this->canManageMfa()) {
            return back()->with('error', 'Only Super Administrators and Admins can disable MFA.');
        }

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = auth()->user();

        $user->update([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_backup_codes' => null,
            'two_factor_enabled' => false,
            'mfa_attempts' => 0,
            'mfa_locked_until' => null,
        ]);

        \Log::info('MFA disabled by super admin', [
            'admin_id' => auth()->id(),
            'user_id' => $user->id,
        ]);

        return back()->with('success', 'Two-Factor Authentication disabled.');
    }
}
