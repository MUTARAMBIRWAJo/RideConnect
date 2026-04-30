<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication — RideConnect</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --rc-primary: #166534;
            --rc-slate-100: #e2e8f0;
            --rc-slate-500: #475569;
            --rc-slate-900: #0f172a;
            --rc-white: #ffffff;
            --rc-bg: #f2f4f8;
            --rc-danger: #dc2626;
            --rc-success: #15803d;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--rc-bg);
            color: var(--rc-slate-900);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 420px;
            background: var(--rc-white);
            border: 1px solid var(--rc-slate-100);
            border-radius: 14px;
            box-shadow: 0 10px 28px rgba(2, 6, 23, 0.08);
            padding: 36px 34px;
        }

        .header {
            text-align: center;
            margin-bottom: 28px;
        }

        .icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 16px;
            background: #f0fdf4;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        h1 {
            font-size: 1.75rem;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .subtitle {
            color: var(--rc-slate-500);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .alert {
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.92rem;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #fee2e2;
            color: #7f1d1d;
            border: 1px solid #fca5a5;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e3a8a;
            border: 1px solid #93c5fd;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 6px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--rc-slate-100);
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Inter', monospace;
            letter-spacing: 0.1em;
            transition: all 0.2s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--rc-primary);
            box-shadow: 0 0 0 3px rgba(22, 101, 52, 0.1);
        }

        input[type="text"]::placeholder {
            letter-spacing: normal;
        }

        .form-group input.code-input {
            font-size: 1.5rem;
            text-align: center;
            letter-spacing: 0.5em;
        }

        button {
            width: 100%;
            padding: 12px;
            background: var(--rc-primary);
            color: var(--rc-white);
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 8px;
        }

        button:hover {
            background: #15543d;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(22, 101, 52, 0.2);
        }

        button:active {
            transform: translateY(0);
        }

        .backup-link {
            text-align: center;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--rc-slate-100);
        }

        .backup-link a {
            color: var(--rc-primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .backup-link a:hover {
            text-decoration: underline;
        }

        .hidden {
            display: none;
        }

        .resend-info {
            font-size: 0.85rem;
            color: var(--rc-slate-500);
            margin-top: 12px;
            text-align: center;
        }

        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 12px 0;
        }

        .checkbox-group label {
            margin: 0;
            font-weight: 400;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">🔐</div>
            <h1>Two-Factor Authentication</h1>
            <p class="subtitle">For your security, please enter your 6-digit verification code.</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-error">
                {{ $errors->first() }}
            </div>
        @endif

        @if (session('info'))
            <div class="alert alert-info">
                {{ session('info') }}
            </div>
        @endif

        <!-- TOTP Code Form -->
        <form id="otp-form" method="POST" action="{{ route('auth.two-factor-verify') }}">
            @csrf

            <div class="form-group">
                <label for="code">Verification Code</label>
                <input 
                    type="text" 
                    id="code" 
                    name="code" 
                    class="code-input"
                    placeholder="000000"
                    inputmode="numeric"
                    autocomplete="off"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    required
                    autofocus
                >
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Trust this device for 30 days</label>
            </div>

            <button type="submit">Verify</button>
        </form>

        <!-- Backup Code Form (hidden by default) -->
        <form id="backup-form" method="POST" action="{{ route('auth.two-factor-backup') }}" class="hidden">
            @csrf

            <div class="form-group">
                <label for="backup_code">Backup Code</label>
                <input 
                    type="text" 
                    id="backup_code" 
                    name="code" 
                    placeholder="e.g., ABC12345"
                    autocomplete="off"
                    required
                >
            </div>

            <button type="submit">Verify Backup Code</button>
        </form>

        <div class="backup-link">
            <a href="#" onclick="toggleForms(event)" class="toggle-backup" id="toggle-text">
                Don't have your authenticator? Use a backup code
            </a>
        </div>

        <div class="resend-info">
            Your code refreshes every 30 seconds. Make sure your device time is correct.
        </div>
    </div>

    <script>
        function toggleForms(e) {
            e.preventDefault();
            const otpForm = document.getElementById('otp-form');
            const backupForm = document.getElementById('backup-form');
            const toggle = document.getElementById('toggle-text');

            otpForm.classList.toggle('hidden');
            backupForm.classList.toggle('hidden');

            if (otpForm.classList.contains('hidden')) {
                toggle.textContent = 'Use authenticator instead';
                document.getElementById('backup_code').focus();
            } else {
                toggle.textContent = "Don't have your authenticator? Use a backup code";
                document.getElementById('code').focus();
            }
        }

        // Auto-format code input
        document.getElementById('code').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });
    </script>
</body>
</html>
