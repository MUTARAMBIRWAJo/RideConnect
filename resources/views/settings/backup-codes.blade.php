@extends('layouts.app')

@section('content')
<div class="backup-codes-container">
    <div class="backup-header">
        <a href="{{ route('mfa.settings') }}" class="back-link">← Back to Settings</a>
        <h1>Backup Codes</h1>
        <p class="subtitle">Save these codes in a safe place. Each code can only be used once.</p>
    </div>

    @if(session('backup_codes'))
        <div class="backup-alert alert-success">
            <strong>✓ Success!</strong> Your backup codes have been generated. Save them immediately.
        </div>
    @endif

    <div class="backup-card">
        <div class="card-header">
            <h2>Your Backup Codes</h2>
            <button onclick="copyAllCodes()" class="btn-copy">📋 Copy All</button>
        </div>

        <div class="card-body">
            <div class="codes-list" id="codesContainer">
                @forelse($user->two_factor_backup_codes ?? session('backup_codes', []) as $index => $code)
                    <div class="code-item" data-code="{{ $code }}">
                        <span class="code-number">{{ sprintf('%02d', $index + 1) }}</span>
                        <code class="code-text">{{ $code }}</code>
                        <button type="button" onclick="copyCode(this)" class="btn-copy-item" title="Copy this code">
                            📋
                        </button>
                    </div>
                @empty
                    <p class="text-muted">No backup codes available. Please enable Two-Factor Authentication first.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="warning-card">
        <div class="card-header">
            <h2>⚠️ Important</h2>
        </div>

        <div class="card-body">
            <ul class="warning-list">
                <li><strong>Save these codes:</strong> Store them in a secure location like a password manager, encrypted file, or safe</li>
                <li><strong>One-time use:</strong> Each backup code can only be used once</li>
                <li><strong>Account recovery:</strong> Use these codes if you lose access to your authenticator app</li>
                <li><strong>Generate new codes:</strong> Disable and re-enable MFA to generate new codes</li>
                <li><strong>Keep them private:</strong> Never share these codes with anyone</li>
            </ul>
        </div>
    </div>

    <div class="button-group">
        <a href="{{ route('mfa.settings') }}" class="btn btn-secondary">Done</a>
        <button onclick="printCodes()" class="btn btn-secondary">🖨️ Print</button>
    </div>
</div>

<style>
    .backup-codes-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 2rem;
    }

    .backup-header {
        margin-bottom: 2rem;
    }

    .back-link {
        color: #166534;
        text-decoration: none;
        font-weight: 600;
        margin-bottom: 1rem;
        display: inline-block;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    .backup-header h1 {
        font-size: 2rem;
        margin: 0.5rem 0;
        color: #0f172a;
    }

    .subtitle {
        color: #64748b;
        font-size: 1rem;
        margin: 0;
    }

    .backup-alert {
        padding: 1rem;
        border-radius: 8px;
        margin: 1.5rem 0;
        background: #dcfce7;
        border: 1px solid #bbf7d0;
        color: #14532d;
    }

    .backup-card,
    .warning-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        margin-bottom: 1.5rem;
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
        font-size: 1.1rem;
        margin: 0;
        color: #0f172a;
    }

    .card-body {
        padding: 1.5rem;
    }

    .codes-list {
        display: grid;
        gap: 0.75rem;
    }

    .code-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.75rem;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-family: 'Courier New', monospace;
    }

    .code-number {
        min-width: 2rem;
        color: #64748b;
        font-weight: 600;
    }

    .code-text {
        flex: 1;
        letter-spacing: 0.1em;
        color: #0f172a;
        font-size: 1rem;
        user-select: all;
    }

    .btn-copy-item {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 1rem;
        padding: 0.5rem;
        border-radius: 4px;
        transition: background 0.2s;
    }

    .btn-copy-item:hover {
        background: #e2e8f0;
    }

    .btn-copy {
        background: #166534;
        color: #fff;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        font-size: 0.875rem;
        transition: background 0.2s;
    }

    .btn-copy:hover {
        background: #14532d;
    }

    .warning-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .warning-list li {
        padding: 0.75rem 0;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
        line-height: 1.6;
    }

    .warning-list li:last-child {
        border-bottom: none;
    }

    .button-group {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
    }

    .btn {
        flex: 1;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        text-align: center;
        font-size: 0.95rem;
    }

    .btn-secondary {
        background: #f1f5f9;
        color: #0f172a;
        border: 1px solid #cbd5e1;
    }

    .btn-secondary:hover {
        background: #e2e8f0;
    }

    .text-muted {
        color: #64748b;
        text-align: center;
        padding: 2rem 0;
    }

    @media (max-width: 768px) {
        .backup-codes-container {
            padding: 1rem;
        }

        .card-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .button-group {
            flex-direction: column;
        }

        .btn {
            width: 100%;
        }
    }

    @media print {
        .back-link,
        .button-group,
        .btn-copy-item {
            display: none;
        }

        .code-item {
            page-break-inside: avoid;
        }
    }
</style>

<script>
    function copyCode(button) {
        const code = button.previousElementSibling.textContent.trim();
        navigator.clipboard.writeText(code).then(() => {
            const originalText = button.textContent;
            button.textContent = '✓ Copied';
            setTimeout(() => {
                button.textContent = originalText;
            }, 2000);
        });
    }

    function copyAllCodes() {
        const codes = Array.from(document.querySelectorAll('.code-text'))
            .map(el => el.textContent.trim())
            .join('\n');
        
        navigator.clipboard.writeText(codes).then(() => {
            alert('All codes copied to clipboard!');
        });
    }

    function printCodes() {
        window.print();
    }
</script>
@endsection
