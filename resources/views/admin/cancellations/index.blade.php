@extends('layouts.app')

@section('title', 'Trip Cancellations')
@section('page-title', 'Trip Cancellations')

@section('content')
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-2xl font-bold text-gray-900">Trip Cancellations</h2>
        </div>

        <form method="GET" class="p-6 flex gap-4 border-b border-gray-200">
            <select name="reason" class="border border-gray-300 rounded-md px-3 py-2">
                <option value="">All Reasons</option>
                <optgroup label="Passenger reasons">
                    @foreach($passengerReasons as $value => $label)
                        <option value="{{ $value }}" @selected(request('reason') === $value)>{{ $label }}</option>
                    @endforeach
                </optgroup>
                <optgroup label="Driver reasons">
                    @foreach($driverReasons as $value => $label)
                        <option value="{{ $value }}" @selected(request('reason') === $value)>{{ $label }}</option>
                    @endforeach
                </optgroup>
            </select>
            <button class="bg-slate-900 text-white px-4 py-2 rounded-md font-semibold">Filter</button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Trip</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Cancelled By</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Reason</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">At</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($cancellations as $cancellation)
                        @php $allReasons = array_merge($passengerReasons, $driverReasons); @endphp
                        <tr>
                            <td class="px-4 py-3 text-sm font-semibold">#{{ $cancellation->id }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($cancellation->trip_id)
                                    <a class="text-green-700 font-semibold" href="{{ route('admin.trips.show', $cancellation->trip_id) }}">#{{ $cancellation->trip_id }}</a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($cancellation->driver_id)
                                    Driver
                                @elseif($cancellation->passenger_id)
                                    Passenger
                                @else
                                    System
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ $allReasons[$cancellation->reason] ?? $cancellation->reason ?? '-' }}
                                @if($cancellation->reason)
                                    <span class="text-gray-500">({{ $cancellation->reason }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $cancellation->cancelled_at?->format('M d, Y H:i') ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-right">
                                <a class="text-green-700 font-semibold" href="{{ route('admin.cancellations.show', $cancellation) }}">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-6 text-sm text-gray-500 text-center" colspan="6">No cancellations found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-6">{{ $cancellations->links() }}</div>
    </div>
@endsection
