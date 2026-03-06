@props(['title'=>'','value'=>null,'meta'=>'','icon'=>null])

<div {{ $attributes->merge(['class'=>'fi-section p-6 rounded-2xl']) }}>
  <div class="flex items-start justify-between">
    <div>
      <div class="text-sm text-gray-500 dark:text-gray-300">{{ $title }}</div>
      <div class="text-3xl font-semibold text-gray-900 dark:text-white">{{ $value ?? '—' }}</div>
      @if($meta)
        <div class="text-sm text-gray-400 mt-1">{{ $meta }}</div>
      @endif
    </div>
    @if($icon)
      <div class="text-2xl">{!! $icon !!}</div>
    @endif
  </div>
</div>
