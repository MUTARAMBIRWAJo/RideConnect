<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-xl border-0 bg-gradient-to-r from-blue-600 via-indigo-600 to-violet-600 p-6 text-white shadow-xl">
            <div class="flex items-center gap-3 mb-2">
                <x-heroicon-o-chart-bar class="w-6 h-6 text-blue-200" />
                <p class="text-xs font-semibold uppercase tracking-wider text-blue-100">AI &amp; Analytics</p>
            </div>
            <h1 class="text-2xl font-bold sm:text-3xl">Business Intelligence Dashboard</h1>
            <p class="mt-2 max-w-2xl text-sm text-blue-100 sm:text-base">
                Real-time revenue, commission, fraud-risk heatmaps, and driver leaderboard powered by live widgets.
            </p>
        </section>

        <x-filament::section>
            <x-slot name="description">These widgets refresh automatically every 60 seconds.</x-slot>
        </x-filament::section>
    </div>
</x-filament-panels::page>
