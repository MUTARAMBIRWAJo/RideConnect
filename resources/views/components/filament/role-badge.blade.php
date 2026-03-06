@php
    $user = auth()->user();
    $roleName = $user?->getRoleNames()?->first();

    if (!$roleName) {
        $rawRole = $user?->role?->value ?? $user?->role;
        $roleName = is_string($rawRole) ? match ($rawRole) {
            'SUPER_ADMIN' => 'Super_admin',
            'ADMIN' => 'Admin',
            'ACCOUNTANT' => 'Accountant',
            'OFFICER' => 'Officer',
            default => $rawRole,
        } : null;
    }

    $badgeClass = match ($roleName) {
        'Super_admin' => 'bg-red-100 text-red-700 ring-red-600/20 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-500/30',
        'Admin' => 'bg-blue-100 text-blue-700 ring-blue-600/20 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30',
        'Accountant' => 'bg-green-100 text-green-700 ring-green-600/20 dark:bg-green-500/15 dark:text-green-300 dark:ring-green-500/30',
        'Officer' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        default => 'bg-gray-100 text-gray-700 ring-gray-600/20 dark:bg-gray-500/15 dark:text-gray-300 dark:ring-gray-500/30',
    };
@endphp

@if ($roleName)
    <span class="fi-role-badge inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $badgeClass }}">
        Role: {{ $roleName }}
    </span>
@endif
