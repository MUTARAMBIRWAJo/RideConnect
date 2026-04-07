@props([
    'title' => 'Metric',
    'value' => '0',
    'subtitle' => null,
    'tone' => 'slate',
])

@php
    $toneMap = [
        'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'amber' => 'border-amber-200 bg-amber-50 text-amber-900',
        'red' => 'border-red-200 bg-red-50 text-red-900',
        'blue' => 'border-blue-200 bg-blue-50 text-blue-900',
        'purple' => 'border-purple-200 bg-purple-50 text-purple-900',
        'slate' => 'border-slate-200 bg-white text-slate-900',
    ];

    $classes = $toneMap[$tone] ?? $toneMap['slate'];
@endphp

<div {{ $attributes->merge(['class' => "rounded-xl border p-4 shadow-sm {$classes}"]) }}>
    <p class="text-xs font-semibold uppercase tracking-wide opacity-80">{{ $title }}</p>
    <p class="mt-2 text-2xl font-bold">{{ $value }}</p>
    @if ($subtitle)
        <p class="mt-1 text-xs opacity-75">{{ $subtitle }}</p>
    @endif
</div>
