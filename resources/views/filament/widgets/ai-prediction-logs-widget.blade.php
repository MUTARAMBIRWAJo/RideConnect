<x-filament-widgets::widget>
    <x-filament::section heading="AI Prediction Logs">
        @if($logs->isEmpty())
            <p class="text-sm text-gray-500">No prediction logs captured yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="px-2 py-2">Type</th>
                            <th class="px-2 py-2">Latency</th>
                            <th class="px-2 py-2">Status</th>
                            <th class="px-2 py-2">Requested</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            <tr class="border-b">
                                <td class="px-2 py-2">{{ $log->prediction_type }}</td>
                                <td class="px-2 py-2">{{ $log->response_time_ms ?? '-' }} ms</td>
                                <td class="px-2 py-2">
                                    <span class="{{ $log->success ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $log->success ? 'success' : 'failed' }}
                                    </span>
                                </td>
                                <td class="px-2 py-2">{{ \Illuminate\Support\Carbon::parse($log->requested_at)->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
