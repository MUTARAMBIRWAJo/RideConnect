@extends('layouts.app')

@section('title', 'Driver Earnings')
@section('page-title', 'Driver Earnings')

@section('content')
    <div class="space-y-6">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
            <a href="{{ route('admin.drivers.show', $driver) }}" class="text-green-700 font-semibold text-sm">
                Back to {{ $driver->user->first_name ?? $driver->user->name ?? 'Driver' }}
            </a>
            <h2 class="mt-2 text-2xl font-bold text-gray-900">Earnings</h2>
        </div>

        @if($driver->wallet)
            @php
                $wallet = $driver->wallet;
                $cards = [
                    ['label' => 'Available Balance', 'field' => 'available_balance', 'value' => $wallet->available_balance, 'color' => 'text-green-700'],
                    ['label' => 'Pending', 'field' => 'pending_balance', 'value' => $wallet->pending_balance, 'color' => 'text-yellow-700'],
                    ['label' => 'Current Balance', 'field' => 'current_balance', 'value' => $wallet->current_balance, 'color' => 'text-blue-700'],
                    ['label' => 'Total Earned', 'field' => 'total_earned', 'value' => $wallet->total_earned, 'color' => 'text-indigo-700'],
                    ['label' => 'Commission Generated', 'field' => 'total_commission_generated', 'value' => $wallet->total_commission_generated, 'color' => 'text-red-600'],
                    ['label' => 'Total Paid', 'field' => 'total_paid', 'value' => $wallet->total_paid, 'color' => 'text-slate-700'],
                ];
            @endphp

            <div class="grid gap-4 md:grid-cols-3">
                @foreach($cards as $card)
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5">
                        <div class="text-xs uppercase text-gray-500 font-semibold">{{ $card['label'] }}</div>
                        <div class="text-xs text-gray-400">{{ $card['field'] }}</div>
                        <div class="mt-3 text-2xl font-bold {{ $card['color'] }}">RWF {{ number_format((float) $card['value'], 0) }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900">Per-Trip Earnings</h3>
                <p class="text-sm text-gray-600">Fields: trip_id, amount, commission, net_amount.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Trip</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Gross (RWF)</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Commission</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Net Paid</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($earnings as $earning)
                            <tr>
                                <td class="px-4 py-3 text-sm">
                                    <a class="text-green-700 font-semibold" href="{{ route('admin.trips.show', $earning->trip_id) }}">#{{ $earning->trip_id }}</a>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ number_format((float) $earning->amount, 0) }}</td>
                                <td class="px-4 py-3 text-sm">-{{ number_format((float) $earning->commission, 0) }}</td>
                                <td class="px-4 py-3 text-sm text-green-700 font-semibold">{{ number_format((float) $earning->net_amount, 0) }}</td>
                                <td class="px-4 py-3 text-sm">{{ $earning->created_at?->format('M d, Y') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-sm text-gray-500 text-center" colspan="5">No earnings recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-6">{{ $earnings->links() }}</div>
        </div>
    </div>
@endsection
