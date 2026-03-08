<x-filament::section>
    <x-slot name="heading">Activity Feed</x-slot>
    <x-slot name="description">Recent operational events</x-slot>

    <div class="space-y-3">
        @forelse($activities as $activity)
            <div class="rounded-xl border border-slate-200/70 dark:border-slate-700 p-3 sm:p-4">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                        {{ $activity['title'] }}
                    </p>
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $activity['time'] }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    {{ $activity['description'] }}
                </p>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 dark:border-slate-700 p-4 text-sm text-slate-500 dark:text-slate-400">
                No recent activity available.
            </div>
        @endforelse
    </div>
</x-filament::section>
