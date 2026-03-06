<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-gray-600">{{ $label }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $value }}</p>
            @if($subtext)
                <p class="text-xs text-gray-500 mt-1">{{ $subtext }}</p>
            @endif
        </div>
        <div class="w-12 h-12 bg-{{ $color ?? 'blue' }}-100 rounded-lg flex items-center justify-center">
            <i class="bi {{ $icon }} text-{{ $color ?? 'blue' }}-600 text-xl"></i>
        </div>
    </div>
    @if($trend)
        <div class="mt-4 flex items-center text-sm">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $trend['color'] }}-100 text-{{ $trend['color'] }}-800">
                {{ $trend['label'] }}
            </span>
        </div>
    @endif
</div>