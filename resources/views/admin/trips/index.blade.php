@extends('layouts.app')

@section('title', 'All Trips')
@section('page-title', 'Trips')

@section('content')
    <div class="space-y-6">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">All Trips</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Monitor trip execution, driver assignment, and payment state.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('admin.trips.create') }}" class="inline-flex items-center rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">
                        Add Trip
                    </a>
                    <a href="{{ route('admin.reviews.index') }}" class="inline-flex items-center rounded-md bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200">
                        Reviews
                    </a>
                    <a href="{{ route('admin.payments.index') }}" class="inline-flex items-center rounded-md bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200">
                        Payments
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @php
                $cards = [
                    ['label' => 'Total Trips', 'value' => $tripStats['total'] ?? 0, 'class' => 'text-blue-700'],
                    ['label' => 'Requested', 'value' => $tripStats['requested'] ?? 0, 'class' => 'text-yellow-700'],
                    ['label' => 'In Progress', 'value' => $tripStats['in_progress'] ?? 0, 'class' => 'text-indigo-700'],
                    ['label' => 'Completed', 'value' => $tripStats['completed'] ?? 0, 'class' => 'text-green-700'],
                ];
            @endphp

            @foreach($cards as $card)
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="text-sm font-medium text-gray-500">{{ $card['label'] }}</div>
                    <div class="mt-2 text-2xl font-bold {{ $card['class'] }}">{{ number_format($card['value']) }}</div>
                </div>
            @endforeach
        </div>

        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <form method="GET" class="grid gap-4 border-b border-gray-200 p-5 md:grid-cols-5">
                <input
                    type="search"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search pickup or destination"
                    class="rounded-md border-gray-300 text-sm"
                >

                <select name="status" class="rounded-md border-gray-300 text-sm">
                    <option value="">All statuses</option>
                    @foreach($statusOptions as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::title(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>

                <select name="transport_type" class="rounded-md border-gray-300 text-sm">
                    <option value="">All transport</option>
                    @foreach($transportOptions as $transport)
                        <option value="{{ $transport }}" @selected(request('transport_type') === $transport)>{{ Str::upper($transport) }}</option>
                    @endforeach
                </select>

                <select name="payment_status" class="rounded-md border-gray-300 text-sm">
                    <option value="">All payments</option>
                    @foreach($paymentStatuses as $paymentStatus)
                        <option value="{{ $paymentStatus }}" @selected(request('payment_status') === $paymentStatus)>{{ Str::title($paymentStatus) }}</option>
                    @endforeach
                </select>

                <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Filter
                </button>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Trip</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Passenger</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Driver</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Pickup</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Destination</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Payment</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Fare</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($trips as $trip)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-semibold text-gray-900">#{{ $trip->id }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">
                                    {{ $trip->passenger->first_name ?? $trip->passenger->name ?? '-' }}
                                    {{ $trip->passenger->last_name ?? '' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">
                                    {{ $trip->driver->user->first_name ?? $trip->driver->user->name ?? '-' }}
                                    {{ $trip->driver->user->last_name ?? '' }}
                                </td>
                                <td class="max-w-xs px-4 py-3 text-sm text-gray-700">{{ Str::limit($trip->pickup_location ?? $trip->pickup_place_name ?? '-', 42) }}</td>
                                <td class="max-w-xs px-4 py-3 text-sm text-gray-700">{{ Str::limit($trip->dropoff_location ?? $trip->dropoff_place_name ?? '-', 42) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm">
                                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">
                                        {{ Str::title(str_replace('_', ' ', $trip->status ?? '-')) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{{ Str::title($trip->payment_status ?? '-') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">RWF {{ number_format((float) ($trip->fare ?? $trip->actual_fare ?? 0), 0) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold">
                                    <a href="{{ route('admin.trips.show', $trip) }}" class="text-green-700 hover:text-green-800">View</a>
                                    <a href="{{ route('admin.trips.edit', $trip) }}" class="ml-3 text-slate-700 hover:text-slate-900">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-sm text-gray-500">No trips found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 p-5">
                {{ $trips->links() }}
            </div>
        </div>
    </div>
@endsection
