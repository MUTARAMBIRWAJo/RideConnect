@extends('layouts.app')

@section('title', 'Driver Profile')
@section('page-title', 'Driver Profile')

@section('content')
    <div class="max-w-4xl bg-white border border-gray-200 rounded-lg shadow-sm p-6">
        <h2 class="text-2xl font-bold text-gray-900">
            {{ $driver->user->first_name ?? $driver->user->name ?? 'Driver' }} {{ $driver->user->last_name ?? '' }}
        </h2>
        <p class="text-sm text-gray-600">Driver #{{ $driver->id }}</p>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <div><strong>Status:</strong> {{ $driver->status ?? '-' }}</div>
            <div><strong>Availability:</strong> {{ $driver->availability_status ?? '-' }}</div>
            <div><strong>Rating:</strong> {{ $driver->rating ?? '0.00' }}</div>
            <div><strong>Trips:</strong> {{ $driver->total_rides ?? 0 }}</div>
        </div>

        <form method="POST" action="{{ route('admin.drivers.availability', $driver) }}" class="mt-6 flex gap-3 items-end">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-semibold text-gray-700">availability_status</label>
                <select name="availability_status" class="mt-1 border border-gray-300 rounded-md px-3 py-2">
                    <option value="online" @selected($driver->availability_status === 'online')>Online</option>
                    <option value="offline" @selected($driver->availability_status === 'offline')>Offline</option>
                    <option value="busy" @selected($driver->availability_status === 'busy')>Busy</option>
                </select>
            </div>
            <button class="bg-green-700 text-white px-4 py-2 rounded-md font-semibold">Update</button>
        </form>

        <div class="mt-6 flex gap-3">
            <a href="{{ route('admin.drivers.earnings', $driver) }}" class="bg-slate-900 text-white px-4 py-2 rounded-md font-semibold">Earnings</a>
            <a href="{{ route('admin.drivers.behavior', $driver) }}" class="bg-slate-900 text-white px-4 py-2 rounded-md font-semibold">Behavior</a>
        </div>
    </div>
@endsection
