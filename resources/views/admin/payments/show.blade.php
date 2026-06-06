@extends('layouts.app')

@section('title', "Payment #{$payment->id}")
@section('page-title', "Payment #{$payment->id}")

@section('content')
    <div class="max-w-4xl bg-white border border-gray-200 rounded-lg shadow-sm p-6">
        <a href="{{ route('admin.payments.index') }}" class="text-green-700 font-semibold text-sm">Back to Payments</a>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div><strong>Trip:</strong> {{ $payment->trip_id ? "#{$payment->trip_id}" : '-' }}</div>
            <div><strong>Status:</strong> {{ Str::upper($payment->status ?? '-') }}</div>
            <div><strong>Method:</strong> {{ Str::upper($payment->payment_method ?? '-') }}</div>
            <div><strong>Provider:</strong> {{ $payment->payment_provider ?? '-' }}</div>
            <div><strong>Amount:</strong> RWF {{ number_format((float) $payment->amount, 0) }}</div>
            <div><strong>Driver Amount:</strong> RWF {{ number_format((float) $payment->driver_amount, 0) }}</div>
            <div><strong>Platform Fee:</strong> RWF {{ number_format((float) $payment->platform_fee, 0) }}</div>
            <div><strong>Paid At:</strong> {{ $payment->paid_at?->format('M d, Y H:i') ?? '-' }}</div>
        </div>
    </div>
@endsection
