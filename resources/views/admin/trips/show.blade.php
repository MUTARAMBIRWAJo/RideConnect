{{-- resources/views/admin/trips/show.blade.php --}}
@extends('layouts.app')
@section('title', "Trip #{{ $trip->id }}")

@section('content')

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  {{-- Header --}}
  <div class="mb-8">
    <div class="flex items-center justify-between">
      <div>
        <div class="text-sm breadcrumb text-gray-600 mb-2">
          <a href="{{ route('admin.trips.index') }}" class="text-indigo-600 hover:text-indigo-700">← All Trips</a>
        </div>
        <h1 class="text-3xl font-bold leading-tight text-gray-900">
          Trip #{{ $trip->id }}
        </h1>
        <p class="mt-2 text-sm text-gray-600">
          Created {{ $trip->created_at?->diffForHumans() }}
          · {{ $trip->transport_type ? Str::upper($trip->transport_type) : 'N/A' }}
        </p>
      </div>

      <a href="{{ route('admin.trips.edit', $trip) }}" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg transition">
        ✏️ Edit
      </a>
    </div>
  </div>

  @if(session('success'))
    <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
      <p class="text-sm text-green-700">✓ {{ session('success') }}</p>
    </div>
  @endif

  {{-- Status banner — same lifecycle Flutter PassengerTrackingScreen shows --}}
  @php
    $statusColors = [
      'requested'         => 'bg-yellow-50 border-yellow-300 text-yellow-800',
      'assigning'         => 'bg-blue-50 border-blue-300 text-blue-800',
      'accepted'          => 'bg-indigo-50 border-indigo-300 text-indigo-800',
      'enroute_to_pickup' => 'bg-purple-50 border-purple-300 text-purple-800',
      'arrived_at_pickup' => 'bg-orange-50 border-orange-300 text-orange-800',
      'in_progress'       => 'bg-cyan-50 border-cyan-300 text-cyan-800',
      'completed'         => 'bg-green-50 border-green-300 text-green-800',
      'cancelled'         => 'bg-red-50 border-red-300 text-red-800',
    ];
    $statusLabels = [
      'requested'         => 'Searching for driver...',
      'assigning'         => 'Matching with a driver...',
      'accepted'          => 'Driver accepted — on the way',
      'enroute_to_pickup' => 'Driver is coming to pickup',
      'arrived_at_pickup' => 'Driver has arrived',
      'in_progress'       => 'Trip in progress',
      'completed'         => 'Trip completed ✓',
      'cancelled'         => 'Trip cancelled',
    ];
  @endphp
  <div class="mb-6 p-4 border rounded-lg {{ $statusColors[$trip->status] ?? 'bg-gray-50 border-gray-200' }}">
    <h3 class="font-semibold mb-1">{{ $statusLabels[$trip->status] ?? Str::title($trip->status) }}</h3>
    <p class="text-xs opacity-75">status: <code class="bg-black bg-opacity-10 px-1 rounded">{{ $trip->status }}</code></p>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    {{-- Left column: Location, Fare, and Passengers --}}
    <div class="md:col-span-2 space-y-6">

      {{-- Location card — mirrors Flutter tracking map info --}}
      <div class="bg-white rounded-xl shadow-sm border p-6">
        <h2 class="text-lg font-semibold text-gray-800 border-b pb-3 mb-4">📍 Location</h2>
        
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Pickup Location</dt>
            <dd class="mt-1 text-gray-900 font-medium">{{ $trip->pickup_location }}</dd>
          </div>

          <div>
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Pickup Lat / Lng</dt>
            <dd class="mt-1 text-gray-900 font-medium">{{ $trip->pickup_lat }}, {{ $trip->pickup_lng }}</dd>
          </div>

          <div class="col-span-2 border-t pt-4">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Dropoff Location</dt>
            <dd class="mt-1 text-gray-900 font-medium">{{ $trip->dropoff_location }}</dd>
          </div>

          <div class="col-span-2">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Dropoff Lat / Lng</dt>
            <dd class="mt-1 text-gray-900 font-medium">{{ $trip->dropoff_lat }}, {{ $trip->dropoff_lng }}</dd>
          </div>

          @if($trip->pickup_zone || $trip->dropoff_zone)
          <div class="col-span-2 border-t pt-4">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Zones</dt>
            <dd class="mt-1 text-gray-900 font-medium">
              {{ $trip->pickup_zone ?? '—' }} → {{ $trip->dropoff_zone ?? '—' }}
            </dd>
          </div>
          @endif
        </div>
      </div>

      {{-- Fare & Payment — mirrors Flutter bottom sheet fare display --}}
      <div class="bg-white rounded-xl shadow-sm border p-6">
        <h2 class="text-lg font-semibold text-gray-800 border-b pb-3 mb-4">💳 Fare & Payment</h2>
        
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Estimated Fare</dt>
            <dd class="mt-1 text-gray-900 font-medium">RWF {{ number_format($trip->fare, 0) }}</dd>
          </div>

          @if($trip->actual_fare)
          <div>
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Actual Fare</dt>
            <dd class="mt-1 text-gray-900 font-medium">RWF {{ number_format($trip->actual_fare, 0) }}</dd>
          </div>
          @endif

          @if($trip->actual_distance)
          <div>
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Actual Distance</dt>
            <dd class="mt-1 text-gray-900 font-medium">{{ number_format($trip->actual_distance, 2) }} km</dd>
          </div>
          @endif

          <div class="col-span-2 border-t pt-4">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Payment Status</dt>
            <dd class="mt-1">
              <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $trip->payment_status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                {{ Str::upper($trip->payment_status) }}
              </span>
            </dd>
          </div>

          <div class="col-span-2">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Transport Type</dt>
            <dd class="mt-1 text-gray-900 font-medium">
              <span class="text-xl">{{ ['moto'=>'🏍️','car'=>'🚗','bus'=>'🚌'][$trip->transport_type] ?? '' }}</span>
              {{ Str::upper($trip->transport_type ?? '—') }}
            </dd>
          </div>

          <div class="col-span-2">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Assignment Status</dt>
            <dd class="mt-1 text-gray-900 font-medium">{{ Str::title(str_replace('_',' ',$trip->assignment_status)) }}</dd>
          </div>
        </div>
      </div>

      {{-- Passenger --}}
      <div class="bg-white rounded-xl shadow-sm border p-6">
        <h2 class="text-lg font-semibold text-gray-800 border-b pb-3 mb-4">👤 Passenger</h2>
        
        @if($trip->passenger)
          <div class="space-y-3 text-sm">
            <div>
              <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Name</dt>
              <dd class="mt-1 text-gray-900 font-medium">{{ $trip->passenger->first_name }} {{ $trip->passenger->last_name }}</dd>
            </div>

            <div>
              <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Phone</dt>
              <dd class="mt-1 text-gray-900 font-medium">{{ $trip->passenger->phone }}</dd>
            </div>

            <div>
              <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Email</dt>
              <dd class="mt-1 text-gray-900 font-medium">{{ $trip->passenger->email }}</dd>
            </div>

            <div>
              <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Verification Status</dt>
              <dd class="mt-1">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $trip->passenger->is_verified ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                  {{ $trip->passenger->is_verified ? '✓ Verified' : '✗ Not verified' }}
                </span>
              </dd>
            </div>

            <div class="pt-2 text-xs text-gray-500">
              ID: {{ $trip->passenger_id }}
            </div>
          </div>
        @else
          <p class="text-gray-500 text-sm">Passenger not found (ID: {{ $trip->passenger_id }})</p>
        @endif
      </div>

    </div>

    {{-- Right column: Driver and Quick Actions --}}
    <div class="space-y-6">

      {{-- Driver --}}
      <div class="bg-white rounded-xl shadow-sm border p-6">
        <h2 class="text-lg font-semibold text-gray-800 border-b pb-3 mb-4">🏍️ Driver</h2>
        
        @if($trip->driver)
          <div class="space-y-3 text-sm">
            <div>
              <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Name</dt>
              <dd class="mt-1 text-gray-900 font-medium">{{ $trip->driver->user->first_name ?? '' }} {{ $trip->driver->user->last_name ?? '' }}</dd>
            </div>

            <div>
              <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Phone</dt>
              <dd class="mt-1 text-gray-900 font-medium">{{ $trip->driver->user->phone ?? '' }}</dd>
            </div>

            <div>
              <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">License Plate</dt>
              <dd class="mt-1 text-gray-900 font-medium">{{ $trip->driver->license_plate }}</dd>
            </div>

            <div>
              <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Rating</dt>
              <dd class="mt-1 text-gray-900 font-medium">
                ⭐ {{ number_format($trip->driver->rating, 2) }} ({{ $trip->driver->total_rides }} rides)
              </dd>
            </div>

            @if($trip->ranker_score)
            <div>
              <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">ML Score</dt>
              <dd class="mt-1 text-gray-900 font-medium">{{ $trip->ranker_score }} · {{ $trip->ranker_version }}</dd>
            </div>
            @endif

            <div class="pt-2 text-xs text-gray-500">
              ID: {{ $trip->driver_id }}
            </div>
          </div>
        @else
          <div>
            <p class="text-gray-500 text-sm mb-4">No driver assigned yet.</p>
            
            @if($availableDrivers->isNotEmpty())
            <form method="POST" action="{{ route('admin.trips.update', $trip) }}" class="space-y-3">
              @csrf
              @method('PUT')
              
              {{-- Preserve other fields when doing quick driver assign --}}
              <input type="hidden" name="pickup_location" value="{{ $trip->pickup_location }}">
              <input type="hidden" name="dropoff_location" value="{{ $trip->dropoff_location }}">
              <input type="hidden" name="pickup_lat" value="{{ $trip->pickup_lat }}">
              <input type="hidden" name="pickup_lng" value="{{ $trip->pickup_lng }}">
              <input type="hidden" name="dropoff_lat" value="{{ $trip->dropoff_lat }}">
              <input type="hidden" name="dropoff_lng" value="{{ $trip->dropoff_lng }}">
              <input type="hidden" name="transport_type" value="{{ $trip->transport_type }}">
              <input type="hidden" name="fare" value="{{ $trip->fare }}">
              <input type="hidden" name="status" value="{{ $trip->status }}">
              <input type="hidden" name="payment_status" value="{{ $trip->payment_status }}">
              <input type="hidden" name="assignment_status" value="{{ $trip->assignment_status }}">
              
              <select name="driver_id" required class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">Select driver...</option>
                @foreach($availableDrivers as $d)
                  <option value="{{ $d->id }}">
                    {{ $d->user->first_name ?? '' }} {{ $d->user->last_name ?? '' }} — {{ $d->license_plate }}
                  </option>
                @endforeach
              </select>
              
              <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-3 rounded-lg transition text-sm">
                Assign Driver
              </button>
            </form>
            @endif
          </div>
        @endif
      </div>

    </div>

  </div>

  {{-- Timeline — mirrors Flutter status progress bar --}}
  <div class="mt-8 bg-white rounded-xl shadow-sm border p-6">
    <h2 class="text-lg font-semibold text-gray-800 border-b pb-3 mb-4">📋 Status Timeline</h2>
    
    <div class="space-y-4">
      @forelse($trip->statusEvents->sortBy('created_at') as $event)
      <div class="border-l-4 border-indigo-400 pl-4 py-2">
        <h4 class="font-medium text-gray-900 text-sm">
          @if($event->old_status)
            {{ Str::title(str_replace('_',' ',$event->old_status))}} → 
          @endif
          {{ Str::title(str_replace('_',' ',$event->new_status)) }}
        </h4>
        <p class="text-xs text-gray-500 mt-1">
          by {{ $event->actor_type ?? 'system' }}
          · {{ $event->created_at->format('M d H:i:s') }}
        </p>
      </div>
      @empty
      <p class="text-sm text-gray-500">No status events yet.</p>
      @endforelse
    </div>
  </div>

</div>

@endsection
