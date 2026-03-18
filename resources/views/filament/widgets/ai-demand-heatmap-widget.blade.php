<x-filament-widgets::widget>
    <x-filament::section heading="Ride Demand Heatmap (Top Zones)">
        @if($zones->isEmpty())
            <p class="text-sm text-gray-500">No demand logs available.</p>
        @else
            <div class="space-y-2">
                @foreach($zones as $zone)
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2">
                        <span class="text-sm">{{ $zone->zone_key }}</span>
                        <span class="text-sm font-semibold">{{ $zone->requests }} requests</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
