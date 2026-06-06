@extends('layouts.app')

@section('title', 'Driver Behavior')
@section('page-title', 'Driver Behavior')

@section('content')
    <div class="space-y-6">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
            <a href="{{ route('admin.drivers.show', $driver) }}" class="text-green-700 font-semibold text-sm">Back to Driver</a>
            <h2 class="mt-2 text-2xl font-bold text-gray-900">Driver Behavior Stats</h2>
            <p class="text-sm text-gray-600">These metrics feed the TFLite ML model as input features for driver ranking.</p>
        </div>

        @if($latest)
            @php
                $metrics = [
                    ['label' => 'Acceptance Rate', 'field' => 'acceptance_rate', 'value' => number_format((float) $latest->acceptance_rate * 100, 1) . '%', 'note' => 'acceptance_rate x 100'],
                    ['label' => 'Cancellation Rate', 'field' => 'cancellation_rate', 'value' => number_format((float) $latest->cancellation_rate * 100, 1) . '%', 'note' => 'cancellation_rate x 100'],
                    ['label' => 'On-Time Rate', 'field' => 'on_time_rate', 'value' => number_format((float) $latest->on_time_rate * 100, 1) . '%', 'note' => 'on_time_rate x 100'],
                    ['label' => 'Behavior Score', 'field' => 'behavior_score', 'value' => number_format((float) $latest->behavior_score, 4), 'note' => '0.0000-1.0000'],
                    ['label' => 'Rating', 'field' => 'rating', 'value' => number_format((float) $latest->rating, 2), 'note' => '0.00-5.00'],
                    ['label' => 'Driving Score', 'field' => 'driving_score', 'value' => $latest->driving_score ? number_format((float) $latest->driving_score, 4) : '-', 'note' => 'ML-computed'],
                ];
            @endphp

            <div class="grid gap-4 md:grid-cols-3">
                @foreach($metrics as $metric)
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5">
                        <div class="text-xs uppercase text-gray-500 font-semibold">{{ $metric['label'] }} ({{ $metric['field'] }})</div>
                        <div class="mt-3 text-2xl font-bold text-slate-900">{{ $metric['value'] }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ $metric['note'] }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Trip</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Rating</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Accept %</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Cancel %</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">On-Time %</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Score</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($behaviors as $behavior)
                            <tr>
                                <td class="px-4 py-3 text-sm">
                                    @if($behavior->trip_id)
                                        <a class="text-green-700 font-semibold" href="{{ route('admin.trips.show', $behavior->trip_id) }}">#{{ $behavior->trip_id }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">{{ number_format((float) $behavior->rating, 2) }}</td>
                                <td class="px-4 py-3 text-sm">{{ number_format((float) $behavior->acceptance_rate * 100, 1) }}%</td>
                                <td class="px-4 py-3 text-sm {{ (float) $behavior->cancellation_rate > 0.1 ? 'text-red-500' : 'text-gray-700' }}">
                                    {{ number_format((float) $behavior->cancellation_rate * 100, 1) }}%
                                </td>
                                <td class="px-4 py-3 text-sm">{{ number_format((float) $behavior->on_time_rate * 100, 1) }}%</td>
                                <td class="px-4 py-3 text-sm">{{ number_format((float) $behavior->behavior_score, 4) }}</td>
                                <td class="px-4 py-3 text-sm">{{ $behavior->created_at?->format('M d, Y') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-sm text-gray-500 text-center" colspan="7">No behavior records yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-6">{{ $behaviors->links() }}</div>
        </div>
    </div>
@endsection
