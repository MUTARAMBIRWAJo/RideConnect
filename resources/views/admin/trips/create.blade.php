{{-- resources/views/admin/trips/create.blade.php --}}
@extends('layouts.app')

@section('title', 'Create Trip - RideConnect Admin')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  {{-- Page header --}}
  <div class="mb-8">
    <div class="text-sm breadcrumb text-gray-600 mb-2">
      <a href="{{ route('admin.trips.index') }}" class="text-indigo-600 hover:text-indigo-700">← Trips</a>
    </div>
    <h1 class="text-3xl font-bold text-gray-900">Create Trip</h1>
  </div>

  <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
    <p class="text-sm text-blue-800">
      ℹ️ This form uses the same field names as the Flutter mobile app's <strong>Book Ride</strong> screen.
      Field names in <code class="bg-blue-100 px-1 rounded">(parentheses)</code> show the exact API key.
    </p>
  </div>

  @if($errors->any())
    <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
      <h3 class="text-sm font-medium text-red-800 mb-2">Please fix the following errors:</h3>
      <ul class="list-disc list-inside space-y-1 text-sm text-red-700">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.trips.store') }}" class="space-y-6">
    @csrf

    {{-- Include the reusable form partial --}}
    @include('admin.trips._form', [
        'trip' => null,
        'transportOptions' => $transportOptions ?? ['moto' => '🏍️ Moto', 'car' => '🚗 Car', 'bus' => '🚌 Bus'],
        'paymentOptions' => $paymentOptions ?? ['cash' => 'Cash', 'momo' => 'MoMo', 'card' => 'Card'],
        'passengers' => $passengers ?? collect(),
        'drivers' => $drivers ?? collect(),
        'statusOptions' => ['requested', 'assigning', 'accepted', 'enroute_to_pickup', 'arrived_at_pickup', 'in_progress', 'completed', 'cancelled'],
        'assignmentStatuses' => ['unassigned', 'assigning', 'assigned', 'failed'],
        'paymentStatuses' => ['unpaid', 'paid', 'refunded'],
    ])

    {{-- Form actions --}}
    <div class="flex gap-3 pt-4 border-t">
      <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg transition">
        Create Trip
      </button>
      <a href="{{ route('admin.trips.index') }}" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-lg text-center transition">
        Cancel
      </a>
    </div>
  </form>
</div>
@endsection
