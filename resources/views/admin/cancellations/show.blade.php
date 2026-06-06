@extends('layouts.app')

@section('title', "Cancellation #{$cancellation->id}")
@section('page-title', "Cancellation #{$cancellation->id}")

@section('content')
    <div class="max-w-4xl bg-white border border-gray-200 rounded-lg shadow-sm p-6">
        <a href="{{ route('admin.cancellations.index') }}" class="text-green-700 font-semibold text-sm">Back to Cancellations</a>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div><strong>Trip:</strong> {{ $cancellation->trip_id ? "#{$cancellation->trip_id}" : '-' }}</div>
            <div><strong>Driver:</strong> {{ $cancellation->driver->user->first_name ?? $cancellation->driver->user->name ?? '-' }}</div>
            <div><strong>Passenger:</strong> {{ $cancellation->passenger_id ?? '-' }}</div>
            <div><strong>Reason:</strong> {{ $cancellation->reason ?? '-' }}</div>
            <div><strong>Cancelled At:</strong> {{ $cancellation->cancelled_at?->format('M d, Y H:i') ?? '-' }}</div>
            <div><strong>Fee:</strong> RWF {{ number_format((float) $cancellation->cancellation_fee, 0) }}</div>
        </div>
    </div>
@endsection
