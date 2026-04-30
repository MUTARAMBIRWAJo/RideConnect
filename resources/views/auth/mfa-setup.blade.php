<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Two-Factor Authentication — RideConnect</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --rc-primary: #166534;
            --rc-slate-100: #e2e8f0;
            --rc-slate-500: #475569;
            --rc-slate-900: #0f172a;
            --rc-white: #ffffff;
            --rc-bg: #f2f4f8;
            --rc-success: #15803d;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--rc-bg);
            color: var(--rc-slate-900);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
        }

        .header {
            margin-bottom: 32px;
        }

        h1 {
            font-size: 2rem;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .subtitle {
            color: var(--rc-slate-500);
            font-size: 1rem;
        }

        .card {
            background: var(--rc-white);
            border: 1px solid var(--rc-slate-100);
            border-radius: 14px;
            padding: 28px;
            margin-bottom: 20px;
        }

        .step {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }

        .step-number {
            min-width: 36px;
            width: 36px;
            height: 36px;
            background: var(--rc-primary);
            color: var(--rc-white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }

        .step-content h3 {
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .step-content p {
            color: var(--rc-slate-500);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .qr-container {
            background: #f8fafc;
            border: 2px dashed var(--rc-slate-100);
            border-radius: 10px;
            padding: 24px;
            text-align: center;
            margin: 24px 0;
        }

        .qr-container img {
            max-width: 100%;
            height: auto;
        }

        .secret-key {
            background: #f8fafc;
            border: 1px solid var(--rc-slate-100);
            border-radius: 8px;
            padding: 12px;
            font-family: 'Courier New', monospace;
            word-break: break-all;
            margin: 16px 0;
            font-size: 0.9rem;
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

        input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--rc-slate-100);
            border-radius: 8px;
            font-size: 1rem;
            text-align: center;
            letter-spacing: 0.1em;
        }

        input:focus {
            outline: none;
            border-color: var(--rc-primary);
            box-shadow: 0 0 0 3px rgba(22, 101, 52, 0.1);
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
        }

        button:hover {
            background: #15543d;
            transform: translateY(-1px);
        }

        .alert {
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #fee2e2;
            color: #7f1d1d;
            border: 1px solid #fca5a5;
        }

        ul {
            margin-left: 20px;
            color: var(--rc-slate-500);
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Enable Two-Factor Authentication</h1>
            <p class="subtitle">Add an extra layer of security to your account</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-error">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="card">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Download an Authenticator App</h3>
                    <p>Use Google Authenticator, Microsoft Authenticator, Authy, or any TOTP-compatible app on your phone.</p>
                </div>
            </div>

            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Scan or Enter Code</h3>
                    <p>Scan the QR code below, or manually enter the secret key:</p>
                    <div class="qr-container">
                        {!! $qrCode !!}
                    </div>
                    <div class="secret-key">{{ $secret }}</div>
                    <p style="color: #666; font-size: 0.85rem; margin-top: 8px;">Save this key in a safe place</p>
                </div>
            </div>

            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Verify Code</h3>
                    <p>Enter the 6-digit code from your authenticator app to complete setup:</p>

                    <form method="POST" action="{{ route('mfa.setup.store') }}" style="margin-top: 16px;">
                        @csrf

                        <div class="form-group">
                            <label for="code">Verification Code</label>
                            <input 
                                type="text" 
                                id="code" 
                                name="code" 
                                placeholder="000000"
                                inputmode="numeric"
                                maxlength="6"
                                pattern="[0-9]{6}"
                                required
                                autofocus
                            >
                        </div>

                        <button type="submit">Enable 2FA</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 12px;">⚠️ Important</h3>
            <ul>
                <li>Save your backup codes in a safe place</li>
                <li>You can use backup codes if you lose access to your authenticator app</li>
                <li>Never share your secret key or backup codes</li>
                <li>Ensure your device time is accurate</li>
            </ul>
        </div>
    </div>

    <script>
        document.getElementById('code').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });
    </script>
</body>
</html>
