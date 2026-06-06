@extends('layouts.app')

@section('title', 'Manager Settings')
@section('page-title', 'Manager Settings')

@section('content')
    <div class="max-w-3xl bg-white border border-gray-200 rounded-lg shadow-sm p-6">
        <h2 class="text-2xl font-bold text-gray-900">System Settings</h2>
        <p class="text-sm text-gray-600">Operational defaults used by manager workflows.</p>

        @if($errors->any())
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 rounded-md p-4">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('manager.settings.update') }}" class="mt-6 space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-semibold text-gray-700">Platform Name</label>
                <input name="platform_name" value="{{ old('platform_name', $settings['platform_name'] ?? 'RideConnect') }}" class="mt-1 w-full border border-gray-300 rounded-md px-3 py-2">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700">Commission Percentage</label>
                <input name="commission_percentage" type="number" step="0.01" min="0" max="100" value="{{ old('commission_percentage', $settings['commission_percentage'] ?? 15) }}" class="mt-1 w-full border border-gray-300 rounded-md px-3 py-2">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700">Currency</label>
                <select name="currency" class="mt-1 w-full border border-gray-300 rounded-md px-3 py-2">
                    @foreach(['RWF', 'EUR', 'GBP'] as $currency)
                        <option value="{{ $currency }}" @selected(old('currency', $settings['currency'] ?? 'RWF') === $currency)>{{ $currency }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700">Email From Address</label>
                <input name="email_from_address" type="email" value="{{ old('email_from_address', $settings['email_from_address'] ?? 'noreply@rideconnect.com') }}" class="mt-1 w-full border border-gray-300 rounded-md px-3 py-2">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700">Email From Name</label>
                <input name="email_from_name" value="{{ old('email_from_name', $settings['email_from_name'] ?? 'RideConnect Support') }}" class="mt-1 w-full border border-gray-300 rounded-md px-3 py-2">
            </div>

            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="maintenance_mode" value="1" @checked(old('maintenance_mode', $settings['maintenance_mode'] ?? false))>
                Maintenance mode
            </label>

            <div>
                <button class="bg-green-700 text-white px-4 py-2 rounded-md font-semibold">Save Settings</button>
            </div>
        </form>
    </div>
@endsection
