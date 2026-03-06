@props(['type' => 'default', 'label' => ''])

@php
  $map = [
    'scheduled' => 'bg-green-100 text-green-800',
    'ongoing' => 'bg-green-100 text-green-800',
    'cancelled' => 'bg-red-100 text-red-800',
    'completed' => 'bg-gray-100 text-gray-800',
  ];
  $cls = $map[$type] ?? 'bg-gray-100 text-gray-800';
@endphp

<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $cls }}">{{ $label }}</span>
