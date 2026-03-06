<div class="fi-section p-6 rounded-2xl">
    <div class="flex items-center justify-between mb-5">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">System Logs</h3>
        <span class="text-xs font-medium tracking-wide uppercase text-gray-500">Recent 8</span>
    </div>

    <div class="space-y-3.5">
        @forelse($logs as $log)
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-3.5">
                <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $log->action ?? 'Log Entry' }}</div>
                <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $log->description ?? 'No description provided.' }}</div>
                <div class="mt-2 text-xs text-gray-400">{{ $log->created_at ?? 'n/a' }}</div>
            </div>
        @empty
            <div class="text-sm text-gray-500">No system logs available.</div>
        @endforelse
    </div>
</div>
