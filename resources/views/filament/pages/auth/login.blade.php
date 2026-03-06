<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>RideConnect — Admin Login</title>
  <link rel="icon" href="{{ asset('images/favicon.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
  <style>body{font-family:Inter,system-ui;background:var(--fi-bg);height:100vh;}</style>
  @vite('resources/js/app.js')
</head>
<body class="flex items-center justify-center">
  <div class="min-h-screen w-full flex items-center justify-center p-6">
      <div class="max-w-md w-full bg-[color-mix(in srgb,var(--fi-card-bg) 80%, transparent)] backdrop-blur-md border border-[rgba(22,101,52,0.18)] rounded-xl p-8">
      <div class="text-center mb-6">
        <img src="{{ asset('images/rideconnect-logo.svg') }}" alt="RideConnect" class="mx-auto h-14 mb-2">
        <div class="text-slate-600 dark:text-slate-300 font-mono tracking-wide">AI-Powered Smart Transport</div>
      </div>

      <form method="POST" action="{{ route('filament.auth.login') }}">
        @csrf
        <div class="mb-4">
          <label class="block text-sm text-slate-700 dark:text-slate-300 mb-1">Email</label>
          <input name="email" type="email" required class="w-full px-3 py-2 rounded bg-[var(--fi-card-bg)] text-slate-900 dark:text-white border border-slate-300 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-green-600" />
        </div>

        <div class="mb-4">
          <label class="block text-sm text-slate-700 dark:text-slate-300 mb-1">Password</label>
          <input name="password" type="password" required class="w-full px-3 py-2 rounded bg-[var(--fi-card-bg)] text-slate-900 dark:text-white border border-slate-300 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-green-600" />
        </div>

        <div class="flex items-center justify-between">
          <button type="submit" class="px-4 py-2 rounded text-white font-semibold bg-gradient-to-r from-green-800 to-green-700 hover:from-green-700 hover:to-green-600 shadow-sm transition-all duration-200">Sign in</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
