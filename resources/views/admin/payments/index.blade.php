@extends('layouts.app')

@section('title', 'Payments')
@section('page-title', 'Payments')

@section('content')
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-2xl font-bold text-gray-900">Payments</h2>
        </div>

        <form method="GET" class="p-6 grid gap-4 md:grid-cols-3 border-b border-gray-200">
            <select name="payment_method" class="border border-gray-300 rounded-md px-3 py-2">
                <option value="">All Methods</option>
                <option value="cash" @selected(request('payment_method') === 'cash')>{{ $paymentMethods['cash'] ?? 'Cash' }}</option>
                <option value="momo" @selected(request('payment_method') === 'momo')>{{ $paymentMethods['momo'] ?? 'MoMo' }}</option>
                <option value="card" @selected(request('payment_method') === 'card')>{{ $paymentMethods['card'] ?? 'Card' }}</option>
            </select>

            <select name="status" class="border border-gray-300 rounded-md px-3 py-2">
                <option value="">All Statuses</option>
                @foreach($paymentStatuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::title($status) }}</option>
                @endforeach
            </select>

            <button class="bg-slate-900 text-white px-4 py-2 rounded-md font-semibold">Filter</button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Trip</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Amount (RWF)</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Driver Gets</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Platform Fee</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Method</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Paid At</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($payments as $payment)
                        <tr>
                            <td class="px-4 py-3 text-sm font-semibold">#{{ $payment->id }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($payment->trip_id)
                                    <a class="text-green-700 font-semibold" href="{{ route('admin.trips.show', $payment->trip_id) }}">#{{ $payment->trip_id }}</a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">{{ number_format((float) $payment->amount, 0) }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format((float) $payment->driver_amount, 0) }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format((float) $payment->platform_fee, 0) }}</td>
                            <td class="px-4 py-3 text-sm">{{ Str::upper($payment->payment_method ?? '-') }}</td>
                            <td class="px-4 py-3 text-sm">
                                @php
                                    $pColors = [
                                        'paid' => 'text-green-600',
                                        'pending' => 'text-yellow-600',
                                        'failed' => 'text-red-600',
                                        'refunded' => 'text-orange-600',
                                    ];
                                @endphp
                                <span class="font-semibold {{ $pColors[$payment->status] ?? '' }}">{{ Str::upper($payment->status ?? '-') }}</span>
                                @if($payment->status === 'pending' && $payment->payment_method === 'cash')
                                    <form method="POST" action="{{ route('admin.payments.mark-paid', $payment) }}" class="inline ml-2">
                                        @csrf
                                        @method('PUT')
                                        <button class="text-green-700 font-semibold">Mark Paid</button>
                                    </form>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $payment->paid_at?->format('M d, H:i') ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-right">
                                <a class="text-green-700 font-semibold" href="{{ route('admin.payments.show', $payment) }}">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-6 text-sm text-gray-500 text-center" colspan="9">No payments found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-6">{{ $payments->links() }}</div>
    </div>
@endsection
