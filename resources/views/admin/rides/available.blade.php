@extends('layouts.app')

@section('title', 'Available Rides - RideConnect Admin')

@section('content')
    <!-- Page Header -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold leading-tight text-gray-900">
                        Available Rides
                    </h1>
                    <p class="mt-2 text-sm text-gray-600">
                        View and manage rides that are currently available for assignment.
                    </p>
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('admin.rides.index') }}" class="bg-blue-100 text-blue-700 hover:bg-blue-200 px-4 py-2 rounded-md text-sm font-medium">
                        All Rides
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
                <!-- Available Rides -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Available Rides</dt>
                                    <dd>
                                        <div class="text-lg font-medium text-gray-900">89</div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ready for Assignment -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Ready for Assignment</dt>
                                    <dd>
                                        <div class="text-lg font-medium text-gray-900">76</div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recently Available -->
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
                                    <dt class="text-sm font-medium text-gray-500 truncate">Recently Available</dt>
                                    <dd>
                                        <div class="text-lg font-medium text-gray-900">13</div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Available Rides Table -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Available Rides</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Rides that are currently available and ready for assignment.</p>
                </div>
                <div class="border-t border-gray-200">
                    <div class="overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ride ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Driver</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Active</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <!-- Sample Available Ride -->
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#RIDE001</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">John Smith</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Toyota Camry - ABC123</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Standard</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Downtown</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2 min ago</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="#" class="text-blue-600 hover:text-blue-900 mr-4">View Details</a>
                                            <a href="#" class="text-green-600 hover:text-green-900 mr-4">Assign to Trip</a>
                                            <a href="#" class="text-yellow-600 hover:text-yellow-900">Track Location</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#RIDE003</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Emma Wilson</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Ford Focus - DEF456</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Standard</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">University Area</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">5 min ago</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="#" class="text-blue-600 hover:text-blue-900 mr-4">View Details</a>
                                            <a href="#" class="text-green-600 hover:text-green-900 mr-4">Assign to Trip</a>
                                            <a href="#" class="text-yellow-600 hover:text-yellow-900">Track Location</a>
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