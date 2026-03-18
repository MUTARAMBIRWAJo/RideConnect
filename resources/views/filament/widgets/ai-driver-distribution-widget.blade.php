<x-filament-widgets::widget>
    <x-filament::section heading="Driver Distribution (Top Zones)">
        @if($distribution->isEmpty())
            <p class="text-sm text-gray-500">No driver location records available.</p>
        @else
            <div class="space-y-2">
                @foreach($distribution as $row)
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2">
                        <span class="text-sm">{{ $row->zone_key }}</span>
                        <span class="text-sm font-semibold">{{ $row->drivers }} drivers</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
