@extends('layouts.app')

@section('title', 'Reviews')
@section('page-title', 'Reviews')

@section('content')
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Reviews</h2>
                <p class="text-sm text-gray-600">Ratings submitted from the Flutter RatingDialog flow.</p>
            </div>
            <a href="{{ route('admin.reviews.create') }}" class="bg-green-700 text-white px-4 py-2 rounded-md text-sm font-semibold">Add Review</a>
        </div>

        <form method="GET" class="p-6 grid gap-4 md:grid-cols-4 border-b border-gray-200">
            <select name="reviewer_type" class="border border-gray-300 rounded-md px-3 py-2">
                <option value="">All Reviewers</option>
                @foreach($reviewerTypes as $rt)
                    <option value="{{ $rt }}" @selected(request('reviewer_type') === $rt)>{{ Str::title($rt) }}</option>
                @endforeach
            </select>

            <select name="rating" class="border border-gray-300 rounded-md px-3 py-2">
                <option value="">All Ratings</option>
                @foreach(range(5, 1) as $r)
                    <option value="{{ $r }}" @selected((string) request('rating') === (string) $r)>
                        {{ str_repeat('*', $r) }}{{ str_repeat('-', 5 - $r) }} ({{ $r }})
                    </option>
                @endforeach
            </select>

            <select name="is_public" class="border border-gray-300 rounded-md px-3 py-2">
                <option value="">Public & Hidden</option>
                <option value="1" @selected(request('is_public') === '1')>Public Only</option>
                <option value="0" @selected(request('is_public') === '0')>Hidden Only</option>
            </select>

            <button class="bg-slate-900 text-white px-4 py-2 rounded-md font-semibold">Filter</button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Trip</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Driver</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Rating</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Safety</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Punctuality</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Reviewer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Visibility</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($reviews as $review)
                        <tr>
                            <td class="px-4 py-3 text-sm font-semibold">#{{ $review->id }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($review->ride_id)
                                    <a class="text-green-700 font-semibold" href="{{ route('admin.trips.show', $review->ride_id) }}">#{{ $review->ride_id }}</a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ $review->driver->user->first_name ?? $review->driver->user->name ?? '-' }}
                                {{ $review->driver->user->last_name ?? '' }}
                            </td>
                            <td class="px-4 py-3 text-sm">{{ str_repeat('*', (int) $review->rating) }}</td>
                            <td class="px-4 py-3 text-sm">{{ $review->safety_rating ? str_repeat('*', (int) $review->safety_rating) : '-' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $review->punctuality_rating ? str_repeat('*', (int) $review->punctuality_rating) : '-' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $review->reviewer_type === 'passenger' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700' }}">
                                    {{ Str::title($review->reviewer_type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $review->is_public ? 'Public' : 'Hidden' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $review->created_at?->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-sm text-right">
                                <a class="text-green-700 font-semibold" href="{{ route('admin.reviews.show', $review) }}">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-6 text-sm text-gray-500 text-center" colspan="10">No reviews found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-6">{{ $reviews->links() }}</div>
    </div>
@endsection
