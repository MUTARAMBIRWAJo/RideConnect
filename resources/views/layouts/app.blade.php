<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'RideConnect') — RideConnect</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/filament/filament/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/filament/forms/forms.css') }}">
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

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: var(--rc-bg);
            color: var(--rc-slate-900);
            line-height: 1.6;
        }

        .app-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        .app-sidebar {
            background: var(--rc-white);
            border-right: 1px solid var(--rc-slate-100);
            padding: 1.5rem;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .app-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 2rem;
            text-decoration: none;
            color: inherit;
            font-weight: 700;
        }

        .app-logo img {
            width: 32px;
            height: 32px;
            max-width: 32px;
            object-fit: contain;
            border-radius: 8px;
        }

        .app-nav {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .app-nav-item {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            color: var(--rc-slate-700);
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .app-nav-item:hover,
        .app-nav-item.active {
            background: var(--rc-slate-50);
            color: var(--rc-primary);
        }

        .app-content {
            background: var(--rc-bg);
            overflow-y: auto;
        }

        .app-header {
            background: var(--rc-white);
            border-bottom: 1px solid var(--rc-slate-100);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .app-user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .app-user-info {
            text-align: right;
        }

        .app-user-name {
            font-weight: 600;
            color: var(--rc-slate-900);
            font-size: 0.95rem;
        }

        .app-user-role {
            font-size: 0.8rem;
            color: var(--rc-slate-500);
        }

        .app-logout {
            padding: 0.5rem 1rem;
            background: var(--rc-danger);
            color: var(--rc-white);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }

        .app-logout:hover {
            background: #b91c1c;
        }

        .app-main {
            padding: 2rem;
        }

        @media (max-width: 768px) {
            .app-container {
                grid-template-columns: 1fr;
            }

            .app-sidebar {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                width: 100%;
                height: auto;
                border-right: none;
                border-top: 1px solid var(--rc-slate-100);
                padding: 1rem;
                z-index: 100;
            }

            .app-main {
                padding-bottom: 100px;
            }

            .app-nav {
                flex-direction: row;
                gap: 0.25rem;
            }
        }
    </style>
    @yield('styles')
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="app-sidebar">
            <a href="{{ route('dashboard') }}" class="app-logo">
                <img src="{{ asset('images/logo.svg') }}" alt="RideConnect" onerror="this.style.display='none'">
                <span>RC</span>
            </a>

            <nav class="app-nav">
                <a href="{{ route('dashboard') }}" class="app-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    🏠 Dashboard
                </a>
                <a href="{{ route('mfa.settings') }}" class="app-nav-item {{ request()->routeIs('mfa.*') ? 'active' : '' }}">
                    🔒 Security & MFA
                </a>
                <a href="#" class="app-nav-item">
                    👤 Profile
                </a>
            </nav>

            <hr style="border: none; border-top: 1px solid var(--rc-slate-100); margin: 1rem 0;">

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="app-logout" style="width: 100%;">
                    Logout
                </button>
            </form>
        </aside>

        <!-- Main Content -->
        <div class="app-content">
            <header class="app-header">
                <h1>@yield('page-title', 'Dashboard')</h1>
                <div class="app-user-menu">
                    <div class="app-user-info">
                        <div class="app-user-name">{{ auth()->user()->name }}</div>
                        <div class="app-user-role">{{ auth()->user()->role?->name ?? 'User' }}</div>
                    </div>
                </div>
            </header>

            <main class="app-main">
                @if(session('success'))
                    <div style="background: #dcfce7; border: 1px solid #bbf7d0; color: #14532d; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        ✓ {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        ✕ {{ session('error') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
