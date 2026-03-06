<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — RideConnect</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --rc-bg: #f2f4f8;
            --rc-white: #ffffff;
            --rc-slate-50: #f8fafc;
            --rc-slate-100: #e2e8f0;
            --rc-slate-200: #cbd5e1;
            --rc-slate-500: #475569;
            --rc-slate-700: #334155;
            --rc-slate-900: #0f172a;
            --rc-primary: #166534;
            --rc-primary-deep: #14532d;
            --rc-success: #15803d;
            --rc-danger: #dc2626;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: var(--rc-bg);
            color: var(--rc-slate-900);
            min-height: 100vh;
        }

        .layout {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1.05fr 1fr;
        }

        .left-panel {
            background: var(--rc-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .register-card {
            width: 100%;
            max-width: 620px;
            background: var(--rc-white);
            border: 1px solid var(--rc-slate-100);
            border-radius: 14px;
            box-shadow: 0 10px 28px rgba(2, 6, 23, 0.08);
            padding: 34px 30px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            text-decoration: none;
            color: inherit;
        }

        .brand img {
            width: 44px;
            height: 44px;
            object-fit: contain;
            border-radius: 999px;
            border: 1px solid var(--rc-slate-100);
            background: var(--rc-white);
        }

        .brand strong { font-size: 1.95rem; letter-spacing: 0.01em; }

        h1 {
            font-size: 2rem;
            line-height: 1.15;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .subtitle {
            color: var(--rc-slate-500);
            margin-bottom: 20px;
            font-size: 1rem;
        }

        .alert {
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.92rem;
            margin-bottom: 14px;
        }

        .alert-error {
            color: #991b1b;
            background: #fee2e2;
            border: 1px solid #fecaca;
        }

        .alert-info {
            color: #14532d;
            background: #dcfce7;
            border: 1px solid #bbf7d0;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .field { margin-bottom: 14px; }
        .field.full { grid-column: 1 / -1; }

        .field label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.93rem;
            font-weight: 600;
            color: var(--rc-slate-700);
        }

        .input {
            width: 100%;
            border: 1px solid var(--rc-slate-200);
            background: var(--rc-slate-50);
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 0.95rem;
            color: var(--rc-slate-900);
            outline: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .input:focus {
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.18);
            background: #fff;
        }

        .input.is-invalid { border-color: #f87171; }

        .error-text {
            color: var(--rc-danger);
            margin-top: 6px;
            font-size: 0.82rem;
        }

        .role-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 8px;
        }

        .role-option {
            border: 1px solid var(--rc-slate-200);
            border-radius: 10px;
            padding: 12px;
            background: var(--rc-slate-50);
            display: flex;
            gap: 8px;
            align-items: center;
            font-size: 0.9rem;
        }

        .terms {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--rc-slate-700);
            margin: 8px 0 16px;
        }

        .terms a {
            color: var(--rc-slate-700);
            text-decoration: none;
            font-weight: 500;
        }

        .terms a:hover {
            color: var(--rc-primary);
            text-decoration: underline;
        }

        .btn {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 12px 14px;
            background: linear-gradient(135deg, var(--rc-primary) 0%, var(--rc-primary-deep) 100%);
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(20, 83, 45, .24);
        }

        .btn:hover { filter: brightness(1.03); }

        .helper {
            margin-top: 14px;
            text-align: center;
            color: var(--rc-slate-500);
            font-size: 0.92rem;
        }

        .helper a {
            color: var(--rc-slate-700);
            text-decoration: none;
            font-weight: 500;
        }

        .helper a:hover {
            color: var(--rc-primary);
            text-decoration: underline;
        }

        .right-panel {
            position: relative;
            padding: 42px 46px;
            color: #ecfdf5;
            background:
                linear-gradient(0deg, rgba(255,255,255,.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px),
                linear-gradient(160deg, #14532d 0%, #166534 58%, #15803d 100%);
            background-size: 20px 20px, 20px 20px, auto;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .top-lang {
            text-align: right;
            font-size: 0.94rem;
            opacity: .95;
            font-weight: 600;
        }

        .hero {
            max-width: 600px;
            margin: 0 auto;
            width: 100%;
            text-align: center;
        }

        .hero h2 {
            font-size: 2.6rem;
            line-height: 1.2;
            letter-spacing: -0.02em;
            margin-bottom: 26px;
            color: #fff;
        }

        .hero-art {
            width: 100%;
            max-width: 520px;
            margin: 0 auto 26px;
            border-radius: 16px;
            padding: 18px;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.16);
            backdrop-filter: blur(2px);
        }

        .hero p {
            text-align: left;
            font-size: 1.1rem;
            line-height: 1.7;
            color: #d1fae5;
            max-width: 600px;
            margin: 0 auto;
        }

        @media (max-width: 1120px) {
            .layout { grid-template-columns: 1fr; }
            .right-panel { display: none; }
            .left-panel { padding: 24px; }
            .register-card { max-width: 720px; }
        }

        @media (max-width: 640px) {
            .grid { grid-template-columns: 1fr; }
            .role-grid { grid-template-columns: 1fr; }
            .register-card { padding: 24px 18px; }
        }
    </style>
</head>
<body>
    <div class="layout">
        <section class="left-panel">
            <div class="register-card">
                <a href="{{ route('home') }}" class="brand">
                    <img src="{{ asset('images/rideconnect-logo.svg') }}" alt="RideConnect" onerror="this.style.display='none'">
                    <strong>RideConnect</strong>
                </a>

                <h1>Create account</h1>
                <p class="subtitle">Join RideConnect to book rides or offer transport services.</p>

                @if (session('info'))
                    <div class="alert alert-info">{{ session('info') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-error">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('register') }}">
                    @csrf

                    <div class="grid">
                        <div class="field full">
                            <label for="name">Full Name</label>
                            <input id="name" type="text" name="name" value="{{ old('name') }}" class="input @error('name') is-invalid @enderror" required autofocus>
                            @error('name')<div class="error-text">{{ $message }}</div>@enderror
                        </div>

                        <div class="field full">
                            <label for="email">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" class="input @error('email') is-invalid @enderror" required autocomplete="email">
                            @error('email')<div class="error-text">{{ $message }}</div>@enderror
                        </div>

                        <div class="field">
                            <label for="password">Password</label>
                            <input id="password" type="password" name="password" class="input @error('password') is-invalid @enderror" required autocomplete="new-password">
                            @error('password')<div class="error-text">{{ $message }}</div>@enderror
                        </div>

                        <div class="field">
                            <label for="password_confirmation">Confirm Password</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" class="input @error('password_confirmation') is-invalid @enderror" required autocomplete="new-password">
                            @error('password_confirmation')<div class="error-text">{{ $message }}</div>@enderror
                        </div>

                        <div class="field full">
                            <label>I want to</label>
                            <div class="role-grid">
                                <label class="role-option">
                                    <input type="radio" name="role" value="passenger" {{ old('role') === 'passenger' ? 'checked' : '' }} required>
                                    <span>Find rides (Passenger)</span>
                                </label>
                                <label class="role-option">
                                    <input type="radio" name="role" value="driver" {{ old('role') === 'driver' ? 'checked' : '' }}>
                                    <span>Offer rides (Driver)</span>
                                </label>
                            </div>
                            @error('role')<div class="error-text">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <label class="terms" for="terms">
                        <input type="checkbox" id="terms" name="terms" {{ old('terms') ? 'checked' : '' }} required>
                        <span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.</span>
                    </label>

                    <button type="submit" class="btn">Create Account</button>
                </form>

                <p class="helper">Already have an account? <a href="{{ route('auth.login') }}">Sign in</a></p>
            </div>
        </section>

        <aside class="right-panel" aria-hidden="true">
            <div class="top-lang">🇬🇧 English</div>

            <div class="hero">
                <h2>RideConnect Passenger & Driver Portal</h2>
                <div class="hero-art">
                    <svg viewBox="0 0 760 360" width="100%" height="auto" role="img" aria-label="Portal illustration">
                        <defs>
                            <linearGradient id="g" x1="0" x2="1" y1="0" y2="1">
                                <stop offset="0%" stop-color="#a7f3d0" stop-opacity=".9"/>
                                <stop offset="100%" stop-color="#dcfce7" stop-opacity=".25"/>
                            </linearGradient>
                        </defs>
                        <circle cx="250" cy="180" r="140" fill="url(#g)" opacity=".35"/>
                        <rect x="320" y="80" width="360" height="210" rx="14" fill="rgba(255,255,255,.88)"/>
                        <rect x="320" y="80" width="360" height="28" rx="14" fill="rgba(15,23,42,.10)"/>
                        <circle cx="342" cy="94" r="5" fill="#fb7185"/>
                        <circle cx="360" cy="94" r="5" fill="#fbbf24"/>
                        <circle cx="378" cy="94" r="5" fill="#4ade80"/>
                        <rect x="520" y="90" width="130" height="8" rx="4" fill="rgba(15,23,42,.08)"/>
                        <g fill="rgba(15,23,42,.1)">
                            <circle cx="360" cy="145" r="18"/>
                            <rect x="390" y="132" width="250" height="10" rx="5"/>
                            <rect x="390" y="150" width="200" height="8" rx="4"/>
                            <circle cx="360" cy="198" r="18"/>
                            <rect x="390" y="185" width="250" height="10" rx="5"/>
                            <rect x="390" y="203" width="200" height="8" rx="4"/>
                            <circle cx="360" cy="251" r="18"/>
                            <rect x="390" y="238" width="250" height="10" rx="5"/>
                            <rect x="390" y="256" width="200" height="8" rx="4"/>
                        </g>
                        <path d="M135 125h120v120h95" stroke="#bbf7d0" stroke-width="12" fill="none"/>
                        <circle cx="130" cy="125" r="24" fill="none" stroke="#fff" stroke-width="4"/>
                        <circle cx="130" cy="125" r="8" fill="#fff"/>
                        <circle cx="130" cy="245" r="24" fill="none" stroke="#fff" stroke-width="4"/>
                        <path d="M120 246l8 8 14-16" stroke="#fff" stroke-width="4" fill="none"/>
                        <circle cx="130" cy="185" r="24" fill="none" stroke="#fff" stroke-width="4"/>
                        <rect x="120" y="175" width="18" height="18" rx="3" fill="none" stroke="#fff" stroke-width="3"/>
                    </svg>
                </div>
                <p>
                    Sign up once to start requesting rides as a passenger or to provide transport services as a driver.
                    RideConnect keeps operations fast, transparent, and reliable across every trip.
                </p>
            </div>
        </aside>
    </div>
</body>
</html>
