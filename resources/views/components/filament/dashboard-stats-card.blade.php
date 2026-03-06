@props([
    'title' => 'Metric',
    'value' => '0',
    'meta' => null,
    'icon' => null,
    'trend' => null,
])

<div class="fi-section rc-dashboard-stat-card p-6">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $title }}</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $value }}</p>

            @if (filled($meta))
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $meta }}</p>
            @endif
        </div>

        @if (filled($icon))
            <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/20 dark:text-primary-300">
                {!! $icon !!}
            </span>
        @endif
    </div>

    @if (filled($trend))
        <div class="mt-4 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-primary-50 text-primary-700 dark:bg-primary-500/20 dark:text-primary-200">
            {{ $trend }}
        </div>
    @endif
</div>
