{{-- resources/views/admin/trips/edit.blade.php --}}
@extends('layouts.app')
@section('title', "Edit Trip #{{ $trip->id }}")

@section('content')

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  {{-- Page header --}}
  <div class="mb-8 flex items-center justify-between">
    <div>
      <div class="text-sm breadcrumb text-gray-600 mb-2">
        <a href="{{ route('admin.trips.index') }}" class="text-indigo-600 hover:text-indigo-700">← All Trips</a>
      </div>
      <h1 class="text-3xl font-bold text-gray-900">Edit Trip #{{ $trip->id }}</h1>
      <p class="mt-2 text-sm text-gray-600">
        Last updated: {{ $trip->updated_at?->format('M d, Y H:i') }}
      </p>
    </div>
    <div>
      <a href="{{ route('admin.trips.show', $trip) }}" class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">
        View Details →
      </a>
    </div>
  </div>

  @if($errors->any())
    <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
      <h3 class="text-sm font-medium text-red-800 mb-2">Please fix the following errors:</h3>
      <ul class="list-disc list-inside space-y-1 text-sm text-red-700">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.trips.update', $trip) }}" class="space-y-6">
    @csrf
    @method('PUT')

    {{-- Include the reusable form partial --}}
    @include('admin.trips._form', compact('trip'))

    {{-- Form actions --}}
    <div class="flex gap-3 pt-4 border-t">
      <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg transition">
        💾 Save Changes
      </button>
      <a href="{{ route('admin.trips.show', $trip) }}" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-lg text-center transition">
        Cancel
      </a>
    </div>
  </form>
</div>

@endsection
