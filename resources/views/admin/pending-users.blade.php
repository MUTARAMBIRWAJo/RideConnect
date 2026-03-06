@extends('layouts.app')

@section('title', 'Pending Users - RideConnect Admin')

@section('content')
<div class="min-h-screen bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <span class="ml-2 text-xl font-bold text-gray-900">RideConnect Admin</span>
                    </div>
                    <div class="ml-6 flex space-x-4">
                        <a href="{{ route('admin.dashboard') }}" class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <a href="{{ route('admin.users.index') }}" class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium">All Users</a>
                        <a href="{{ route('admin.users.pending') }}" class="bg-green-100 text-green-700 px-3 py-2 rounded-md text-sm font-medium">Pending Approvals</a>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-700">Welcome, {{ Auth::user()->name }}</span>
                        <span class="text-xs text-gray-500">({{ Auth::user()->role->value }})</span>
                        <form action="{{ route('admin.logout') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <div class="py-10">
        <header>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold leading-tight text-gray-900">
                    Pending User Approvals
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    Review and approve user registrations. 
                    @if(Auth::user()->isSuperAdmin())
                        As SuperAdmin, you can approve all users including managers.
                    @else
                        As Admin, you can only approve mobile app users (Passengers and Drivers).
                    @endif
                </p>
            </div>
        </header>
        <main>
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                @if(session('success'))
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                        <strong class="font-bold">Success!</strong>
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                @endif

                <div class="mt-8">
                    @if($users->isEmpty())
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="p-6 text-center text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="mt-2 text-lg">No pending users to approve!</p>
                            </div>
                        </div>
                    @else
                        <div class="bg-white shadow overflow-hidden sm:rounded-md">
                            <ul class="divide-y divide-gray-200">
                                @foreach($users as $user)
                                    <li>
                                        <div class="px-4 py-4 sm:px-6">
                                            <div class="flex items-center justify-between">
                                                <div class="flex flex-col">
                                                    <p class="text-lg font-medium text-green-600 truncate">
                                                        {{ $user->name }}
                                                    </p>
                                                    <p class="text-sm text-gray-500">
                                                        {{ $user->email }} &bull; {{ $user->phone ?? 'No phone' }}
                                                    </p>
                                                </div>
                                                <div class="ml-2 flex-shrink-0 flex">
                                                    <p class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        @if($user->role->isManager()) bg-purple-100 text-purple-800
                                                        @elseif($user->isDriver()) bg-blue-100 text-blue-800
                                                        @else bg-gray-100 text-gray-800
                                                        @endif">
                                                        {{ $user->role->value }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="mt-2 sm:flex sm:justify-between">
                                                <div class="sm:flex">
                                                    <p class="flex items-center text-sm text-gray-500">
                                                        <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        Registered: {{ $user->created_at->diffForHumans() }}
                                                    </p>
                                                </div>
                                                <div class="mt-2 flex items-center sm:mt-0">
                                                    <form action="{{ route('admin.users.approve', $user->id) }}" method="POST" class="inline">
                                                        @csrf
                                                        <button type="submit" class="mr-2 inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:border-green-700 focus:shadow-outline-green active:bg-green-700 transition ease-in-out duration-150">
                                                            Approve
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('admin.users.reject', $user->id) }}" method="POST" class="inline">
                                                        @csrf
                                                        <button type="submit" class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:text-gray-500 focus:outline-none focus:border-gray-300 focus:shadow-outline-gray active:bg-gray-50 transition ease-in-out duration-150">
                                                            Reject
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </main>
    </div>
</div>
@endsection
