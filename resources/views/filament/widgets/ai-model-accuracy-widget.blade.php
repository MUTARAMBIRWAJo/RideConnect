<x-filament-widgets::widget>
    <x-filament::section heading="Model Accuracy">
        @if($metrics->isEmpty())
            <p class="text-sm text-gray-500">No AI model metrics recorded yet.</p>
        @else
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                @foreach($metrics as $metric)
                    <div class="rounded-xl border border-gray-200 p-3">
                        <div class="text-xs text-gray-500">{{ $metric->model_name }}</div>
                        <div class="font-semibold">{{ $metric->metric_name }}</div>
                        <div class="text-xl font-bold">{{ number_format((float) $metric->metric_value, 4) }}</div>
                        <div class="text-xs text-gray-500">{{ \Illuminate\Support\Carbon::parse($metric->evaluated_at)->diffForHumans() }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
