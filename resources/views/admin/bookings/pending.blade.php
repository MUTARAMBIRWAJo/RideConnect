@extends('layouts.app')

@section('title', 'Pending Bookings - RideConnect Admin')

@section('content')
    <!-- Page Header -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold leading-tight text-gray-900">
                        Pending Bookings
                    </h1>
                    <p class="mt-2 text-sm text-gray-600">
                        Review and manage bookings awaiting confirmation.
                    </p>
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('admin.bookings.index') }}" class="bg-blue-100 text-blue-700 hover:bg-blue-200 px-4 py-2 rounded-md text-sm font-medium">
                        All Bookings
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-8">
                <!-- Pending Bookings -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Pending Bookings</dt>
                                    <dd>
                                        <div class="text-lg font-medium text-gray-900">23</div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Awaiting Confirmation -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Awaiting Confirmation</dt>
                                    <dd>
                                        <div class="text-lg font-medium text-gray-900">18</div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Pending -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Payment Pending</dt>
                                    <dd>
                                        <div class="text-lg font-medium text-gray-900">5</div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Bookings Table -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Pending Bookings</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Bookings that require attention and confirmation.</p>
                </div>
                <div class="border-t border-gray-200">
                    <div class="overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ride Type</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pickup</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destination</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <!-- Sample Pending Booking -->
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#BKG023</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Sarah Johnson</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Premium</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Airport Terminal 1</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Downtown Hotel</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Jan 17, 2024 - 3:00 PM</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                Awaiting Confirmation
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="#" class="text-blue-600 hover:text-blue-900 mr-4">View Details</a>
                                            <a href="#" class="text-green-600 hover:text-green-900 mr-4">Confirm</a>
                                            <a href="#" class="text-red-600 hover:text-red-900">Cancel</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#BKG024</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Michael Chen</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Standard</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">University</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Train Station</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Jan 17, 2024 - 5:30 PM</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Payment Pending
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="#" class="text-blue-600 hover:text-blue-900 mr-4">View Details</a>
                                            <a href="#" class="text-green-600 hover:text-green-900 mr-4">Confirm</a>
                                            <a href="#" class="text-red-600 hover:text-red-900">Cancel</a>
                                        </td>
                                    </tr>
                                    <!-- Add more rows as needed -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection