@extends('layouts.app')

@section('title', 'Add Review')
@section('page-title', 'Add Review')

@section('content')
    <div class="max-w-3xl bg-white border border-gray-200 rounded-lg shadow-sm p-6">
        <a href="{{ route('admin.reviews.index') }}" class="text-green-700 font-semibold text-sm">Back to Reviews</a>
        <h2 class="mt-2 text-2xl font-bold text-gray-900">Add Review</h2>
        <p class="text-sm text-gray-600">Field names match the Flutter RatingDialog form.</p>

        @if($errors->any())
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 rounded-md p-4">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.reviews.store') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-semibold text-gray-700">Trip (trip_id / ride_id)</label>
                <select name="trip_id" class="mt-1 w-full border border-gray-300 rounded-md px-3 py-2" required onchange="document.getElementById('ride_id').value = this.value">
                    <option value="">Select completed trip...</option>
                    @foreach($trips as $trip)
                        <option value="{{ $trip->id }}" @selected(old('trip_id') == $trip->id)>Trip #{{ $trip->id }}</option>
                    @endforeach
                </select>
                <input id="ride_id" type="hidden" name="ride_id" value="{{ old('ride_id', old('trip_id')) }}">
                <input type="hidden" name="booking_id" value="{{ old('booking_id', 1) }}">
                <input type="hidden" name="user_id" value="{{ old('user_id', auth()->id()) }}">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700">Driver (driver_id)</label>
                <select name="driver_id" class="mt-1 w-full border border-gray-300 rounded-md px-3 py-2" required>
                    <option value="">Select driver...</option>
                    @foreach($drivers as $driver)
                        <option value="{{ $driver->id }}" @selected(old('driver_id') == $driver->id)>
                            {{ $driver->user->first_name ?? $driver->user->name ?? 'Driver' }} {{ $driver->user->last_name ?? '' }}
                            (rating {{ $driver->rating ?? '0.00' }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700">Reviewer Type (reviewer_type)</label>
                <div class="mt-2 flex gap-4">
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" name="reviewer_type" value="passenger" @checked(old('reviewer_type', 'passenger') === 'passenger')>
                        {{ $reviewerTypes['passenger'] ?? 'Passenger' }}
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" name="reviewer_type" value="driver" @checked(old('reviewer_type') === 'driver')>
                        {{ $reviewerTypes['driver'] ?? 'Driver' }}
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700">Overall Rating (rating)</label>
                <select name="rating" class="mt-1 w-full border border-gray-300 rounded-md px-3 py-2" required>
                    @foreach(range(1, 5) as $rating)
                        <option value="{{ $rating }}" @selected(old('rating') == $rating)>{{ $rating }}</option>
                    @endforeach
                </select>
            </div>

            @php
                $subRatings = [
                    'safety_rating' => 'Safety',
                    'punctuality_rating' => 'Punctuality',
                    'communication_rating' => 'Communication',
                    'vehicle_condition_rating' => 'Vehicle Condition',
                ];
            @endphp

            @foreach($subRatings as $field => $label)
                <div>
                    <label class="block text-sm font-semibold text-gray-700">{{ $label }} ({{ $field }})</label>
                    <select name="{{ $field }}" class="mt-1 w-full border border-gray-300 rounded-md px-3 py-2">
                        <option value="">None</option>
                        @foreach(range(1, 5) as $rating)
                            <option value="{{ $rating }}" @selected(old($field) == $rating)>{{ $rating }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach

            <div>
                <label class="block text-sm font-semibold text-gray-700">Comment (comment)</label>
                <textarea name="comment" rows="4" class="mt-1 w-full border border-gray-300 rounded-md px-3 py-2">{{ old('comment') }}</textarea>
            </div>

            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="is_public" value="1" @checked(old('is_public', true))>
                Make public (is_public)
            </label>

            <div class="flex gap-3">
                <a href="{{ route('admin.reviews.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md font-semibold">Cancel</a>
                <button class="bg-green-700 text-white px-4 py-2 rounded-md font-semibold">Submit Review</button>
            </div>
        </form>
    </div>
@endsection
