<x-filament::section>
    <x-slot name="heading">Notifications</x-slot>
    <x-slot name="description">Role-specific work queue counters</x-slot>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        @foreach($items as $item)
            <div class="rounded-xl border border-slate-200/70 dark:border-slate-700 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $item['label'] }}</p>
                <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $item['value'] }}</p>
            </div>
        @endforeach
    </div>
</x-filament::section>
