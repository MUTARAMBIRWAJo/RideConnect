@extends('layouts.app')

@section('title', "Review #{$review->id}")
@section('page-title', "Review #{$review->id}")

@section('content')
    <div class="max-w-4xl bg-white border border-gray-200 rounded-lg shadow-sm p-6">
        <a href="{{ route('admin.reviews.index') }}" class="text-green-700 font-semibold text-sm">Back to Reviews</a>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div><strong>Driver:</strong> {{ $review->driver->user->first_name ?? $review->driver->user->name ?? '-' }} {{ $review->driver->user->last_name ?? '' }}</div>
            <div><strong>Reviewer:</strong> {{ Str::title($review->reviewer_type) }}</div>
            <div><strong>Rating:</strong> {{ $review->rating }}/5</div>
            <div><strong>Public:</strong> {{ $review->is_public ? 'Yes' : 'No' }}</div>
            <div><strong>Safety:</strong> {{ $review->safety_rating ?? '-' }}</div>
            <div><strong>Punctuality:</strong> {{ $review->punctuality_rating ?? '-' }}</div>
            <div><strong>Communication:</strong> {{ $review->communication_rating ?? '-' }}</div>
            <div><strong>Vehicle Condition:</strong> {{ $review->vehicle_condition_rating ?? '-' }}</div>
        </div>
        <div class="mt-6">
            <h3 class="font-semibold text-gray-900">Comment</h3>
            <p class="mt-2 text-gray-700">{{ $review->comment ?: 'No comment.' }}</p>
        </div>
        @if($review->is_public)
            <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" class="mt-6">
                @csrf
                @method('DELETE')
                <button class="bg-red-600 text-white px-4 py-2 rounded-md font-semibold">Hide Review</button>
            </form>
        @endif
    </div>
@endsection
