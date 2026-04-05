<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">System Monitoring</x-slot>
        <x-slot name="description">API, AI service, database, and queue runtime health.</x-slot>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($checks as $name => $check)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ str_replace('_', ' ', $name) }}</p>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $check['status'] === 'ok' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : ($check['status'] === 'warn' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300') }}">
                            {{ strtoupper($check['status']) }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">{{ $check['message'] }}</p>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
