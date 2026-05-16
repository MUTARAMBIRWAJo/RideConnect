<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class MfaSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'MFA Setup';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.mfa-settings';

    protected static ?string $slug = 'mfa-settings';

    public ?array $data = [];

    public string $qrCode;

    public string $secret;

    public bool $hasMfaEnabled = false;

    public bool $showBackupCodes = false;

    public array $backupCodes = [];

    public function mount(): void
    {
        $this->authorizeAccess();

        $user = Auth::user();
        $this->hasMfaEnabled = $user->hasMfaEnabled();

        // Generate secret and QR code for setup if not already in session and MFA not enabled
        if (! $this->hasMfaEnabled && ! Session::has('mfa_secret')) {
            $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);
            $secret = $google2fa->generateSecretKey();
            Session::put('mfa_secret', $secret);
        } elseif (Session::has('mfa_secret')) {
            $secret = Session::get('mfa_secret');
        }

        if (! $this->hasMfaEnabled && isset($secret)) {
            $this->secret = $secret;
            $google2faQr = app(\PragmaRX\Google2FAQRCode\Google2FA::class);
            $this->qrCode = $google2faQr->getQRCodeInline(
                config('app.name'),
                $user->email,
                $secret
            );
        }

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('code')
                    ->label('Verification Code')
                    ->placeholder('Enter 6-digit code from authenticator app')
                    ->required()
                    ->maxLength(6)
                    ->numeric()
                    ->visible(! $this->hasMfaEnabled),
                TextInput::make('current_password')
                    ->label('Current Password')
                    ->type('password')
                    ->required()
                    ->currentPassword()
                    ->visible($this->hasMfaEnabled),
            ])
            ->statePath('data');
    }

    public function setupMfa(): void
    {
        $this->authorizeAccess();

        if ($this->hasMfaEnabled) {
            Notification::make()
                ->title('MFA already enabled')
                ->warning()
                ->send();

            return;
        }

        $this->validate([
            'data.code' => ['required', 'string', 'digits:6'],
        ]);

        $user = Auth::user();
        $secret = Session::get('mfa_secret');

        if (! $secret) {
            Notification::make()
                ->title('Session expired')
                ->body('Please refresh the page and try again.')
                ->warning()
                ->send();

            return;
        }

        $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);
        $verified = $google2fa->verifyKey($secret, $this->data['code'], 2);

        if (! $verified) {
            Notification::make()
                ->title('Invalid code')
                ->body('Please check the code and try again.')
                ->warning()
                ->send();

            return;
        }

        $backupCodes = collect(range(1, 10))
            ->map(fn () => str()->random(8))
            ->toArray();

        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_backup_codes' => $backupCodes,
            'two_factor_enabled' => true,
        ]);

        Session::forget('mfa_secret');

        $this->hasMfaEnabled = true;
        $this->backupCodes = $backupCodes;
        $this->showBackupCodes = true;

        Notification::make()
            ->title('Two-Factor Authentication enabled')
            ->success()
            ->send();
    }

    public function disableMfa(): void
    {
        $this->authorizeAccess();

        $this->validate([
            'data.current_password' => ['required', 'current_password'],
        ]);

        $user = Auth::user();

        $user->update([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_backup_codes' => null,
            'two_factor_enabled' => false,
            'mfa_attempts' => 0,
            'mfa_locked_until' => null,
        ]);

        $this->hasMfaEnabled = false;
        $this->showBackupCodes = false;
        $this->backupCodes = [];
        $this->secret = '';
        $this->qrCode = '';

        $this->form->fill([]);

        Notification::make()
            ->title('Two-Factor Authentication disabled')
            ->success()
            ->send();
    }

    public function refreshSetup(): void
    {
        $this->authorizeAccess();

        if ($this->hasMfaEnabled) {
            return;
        }

        $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);
        $secret = $google2fa->generateSecretKey();
        Session::put('mfa_secret', $secret);

        $this->secret = $secret;

        $google2faQr = app(\PragmaRX\Google2FAQRCode\Google2FA::class);
        $this->qrCode = $google2faQr->getQRCodeInline(
            config('app.name'),
            Auth::user()->email,
            $secret
        );

        $this->showBackupCodes = false;
        $this->backupCodes = [];
        $this->form->fill([]);

        Notification::make()
            ->title('New setup code generated')
            ->success()
            ->send();
    }

    protected function authorizeAccess(): void
    {
        $user = Auth::user();

        abort_unless($user, 403);

        // Allow SuperAdmin, Admin, Accountant, and Officer roles to access MFA settings
        $allowedRoles = [
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::ACCOUNTANT->value,
            UserRole::OFFICER->value,
        ];

        abort_unless(in_array($user->role?->value, $allowedRoles, true), 403);
    }
}
