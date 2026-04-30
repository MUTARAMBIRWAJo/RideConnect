@extends('layouts.app')

@section('content')
<div class="settings-container">
    <div class="settings-header">
        <h1>Two-Factor Authentication Settings</h1>
        <p class="subtitle">Secure your account with two-factor authentication</p>
    </div>

    <div class="settings-grid">
        <!-- Current Status Card -->
        <div class="status-card">
            <div class="card-header">
                <h2>Current Status</h2>
                <div class="status-badge {{ $user->hasMfaEnabled() ? 'enabled' : 'disabled' }}">
                    {{ $user->hasMfaEnabled() ? '✓ Enabled' : '✗ Disabled' }}
                </div>
            </div>
            
            <div class="card-body">
                @if($user->hasMfaEnabled())
                    <div class="status-info">
                        <p><strong>MFA Type:</strong> Time-based One-Time Password (TOTP)</p>
                        <p><strong>Enabled Since:</strong> {{ $user->two_factor_confirmed_at?->format('M d, Y H:i') ?? 'N/A' }}</p>
                        <p><strong>Backup Codes:</strong> {{ count($user->two_factor_backup_codes ?? []) }} available</p>
                    </div>

                    <div class="alert alert-info">
                        <strong>📱 Using TOTP:</strong> Your account is protected with time-based codes. Use any authenticator app like Google Authenticator, Microsoft Authenticator, or Authy.
                    </div>

                    <div class="button-group">
                        <a href="{{ route('mfa.backup-codes') }}" class="btn btn-secondary">
                            View Backup Codes
                        </a>
                        @if($canDisable ?? false)
                            <form method="POST" action="{{ route('mfa.disable') }}" class="inline-form">
                                @csrf
                                <div class="disable-form">
                                    <input type="password" name="password" placeholder="Enter password to disable" required>
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure? You\'ll lose this protection.')">
                                        Disable MFA
                                    </button>
                                </div>
                            </form>
                        @else
                            <div style="padding: 0.75rem; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; color: #92400e; font-size: 0.9rem;">
                                ℹ️ MFA cannot be disabled by the user. Contact your system administrator if you need to disable MFA.
                            </div>
                        @endif
                    </div>
                @else
                    <div class="status-info">
                        <p>Two-Factor Authentication is currently <strong>disabled</strong>.</p>
                        <p class="text-muted">Enable it to add an extra layer of security to your account.</p>
                    </div>

                    <div class="alert alert-warning">
                        <strong>⚠️ Not Protected:</strong> Your account relies only on password protection. Consider enabling MFA for better security.
                    </div>

                    <a href="{{ route('mfa.setup') }}" class="btn btn-primary">
                        Enable Two-Factor Authentication
                    </a>
                @endif
            </div>
        </div>

        <!-- How It Works -->
        <div class="info-card">
            <div class="card-header">
                <h2>How It Works</h2>
            </div>
            
            <div class="card-body">
                <ol class="steps-list">
                    <li>
                        <strong>Setup:</strong> Scan a QR code with your authenticator app or enter the secret key manually
                    </li>
                    <li>
                        <strong>Generate:</strong> Your app generates a new 6-digit code every 30 seconds
                    </li>
                    <li>
                        <strong>Login:</strong> Enter the code when prompted after password login
                    </li>
                    <li>
                        <strong>Backup:</strong> Save your backup codes in a safe place for account recovery
                    </li>
                </ol>
            </div>
        </div>

        <!-- Recommended Apps -->
        <div class="info-card">
            <div class="card-header">
                <h2>Recommended Authenticator Apps</h2>
            </div>
            
            <div class="card-body">
                <div class="apps-grid">
                    <div class="app-item">
                        <strong>Google Authenticator</strong>
                        <p>Free, simple, and reliable</p>
                        <div class="app-links">
                            <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank">Android</a>
                            <a href="https://apps.apple.com/us/app/google-authenticator/id388497605" target="_blank">iOS</a>
                        </div>
                    </div>
                    <div class="app-item">
                        <strong>Microsoft Authenticator</strong>
                        <p>Integrated with Microsoft account</p>
                        <div class="app-links">
                            <a href="https://play.google.com/store/apps/details?id=com.azure.authenticator" target="_blank">Android</a>
                            <a href="https://apps.apple.com/us/app/microsoft-authenticator/id983156458" target="_blank">iOS</a>
                        </div>
                    </div>
                    <div class="app-item">
                        <strong>Authy</strong>
                        <p>Multi-device sync support</p>
                        <div class="app-links">
                            <a href="https://play.google.com/store/apps/details?id=com.authy.authy" target="_blank">Android</a>
                            <a href="https://apps.apple.com/us/app/authy/id494868301" target="_blank">iOS</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Tips -->
        <div class="info-card">
            <div class="card-header">
                <h2>🔒 Security Tips</h2>
            </div>
            
            <div class="card-body">
                <ul class="tips-list">
                    <li><strong>Keep your phone secure:</strong> MFA is only as secure as your device</li>
                    <li><strong>Save backup codes:</strong> Store them in a secure location (password manager, safe, etc.)</li>
                    <li><strong>Update your app:</strong> Keep your authenticator app updated</li>
                    <li><strong>Use strong password:</strong> MFA complements, not replaces, a strong password</li>
                    <li><strong>Never share codes:</strong> Your 6-digit codes should never be shared</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
    .settings-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 2rem;
    }

    .settings-header {
        margin-bottom: 2rem;
    }

    .settings-header h1 {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        color: #0f172a;
    }

    .subtitle {
        color: #64748b;
        font-size: 1rem;
    }

    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 1.5rem;
    }

    .status-card,
    .info-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .card-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 1.25rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .card-header h2 {
        font-size: 1.25rem;
        margin: 0;
        color: #0f172a;
    }

    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .status-badge.enabled {
        background: #dcfce7;
        color: #14532d;
    }

    .status-badge.disabled {
        background: #fee2e2;
        color: #7f1d1d;
    }

    .card-body {
        padding: 1.5rem;
    }

    .status-info {
        margin-bottom: 1rem;
    }

    .status-info p {
        margin: 0.5rem 0;
        color: #334155;
    }

    .status-info strong {
        color: #0f172a;
    }

    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin: 1rem 0;
        font-size: 0.95rem;
    }

    .alert-info {
        background: #e0f2fe;
        border: 1px solid #bae6fd;
        color: #0c4a6e;
    }

    .alert-warning {
        background: #fef3c7;
        border: 1px solid #fcd34d;
        color: #92400e;
    }

    .button-group {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
        flex-wrap: wrap;
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-block;
        text-align: center;
        font-size: 0.95rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, #166534 0%, #14532d 100%);
        color: #fff;
        box-shadow: 0 4px 12px rgba(20, 83, 45, 0.24);
    }

    .btn-primary:hover {
        filter: brightness(1.05);
    }

    .btn-secondary {
        background: #f1f5f9;
        color: #0f172a;
        border: 1px solid #cbd5e1;
    }

    .btn-secondary:hover {
        background: #e2e8f0;
    }

    .btn-danger {
        background: #dc2626;
        color: #fff;
        flex: 1;
    }

    .btn-danger:hover {
        background: #b91c1c;
    }

    .disable-form {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .disable-form input {
        flex: 1;
        padding: 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 0.95rem;
    }

    .disable-form input:focus {
        outline: none;
        border-color: #22c55e;
        box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.18);
    }

    .inline-form {
        width: 100%;
    }

    .steps-list,
    .tips-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .steps-list li,
    .tips-list li {
        padding: 0.75rem 0;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
        line-height: 1.6;
    }

    .steps-list li:last-child,
    .tips-list li:last-child {
        border-bottom: none;
    }

    .apps-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
    }

    .app-item {
        padding: 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        text-align: center;
    }

    .app-item strong {
        display: block;
        margin-bottom: 0.5rem;
        color: #0f172a;
    }

    .app-item p {
        font-size: 0.875rem;
        color: #64748b;
        margin: 0.5rem 0;
    }

    .app-links {
        display: flex;
        gap: 0.5rem;
        justify-content: center;
        margin-top: 0.75rem;
    }

    .app-links a {
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
        background: #f1f5f9;
        color: #0f172a;
        text-decoration: none;
        border-radius: 6px;
        transition: background 0.2s;
    }

    .app-links a:hover {
        background: #e2e8f0;
    }

    .text-muted {
        color: #64748b;
    }

    @media (max-width: 768px) {
        .settings-grid {
            grid-template-columns: 1fr;
        }

        .disable-form {
            flex-direction: column;
        }

        .disable-form .btn-danger {
            width: 100%;
        }

        .button-group {
            flex-direction: column;
        }

        .button-group .btn {
            width: 100%;
        }
    }
</style>
@endsection
