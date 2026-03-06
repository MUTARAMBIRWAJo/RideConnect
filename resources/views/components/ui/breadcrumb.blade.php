<nav class="flex" aria-label="Breadcrumb">
    <ol class="inline-flex items-center space-x-1 md:space-x-3">
        @foreach($items as $index => $item)
            <li class="inline-flex items-center">
                @if($index < count($items) - 1)
                    <a href="{{ $item['url'] }}" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                        <i class="bi {{ $item['icon'] }} mr-2 text-gray-400"></i>
                        {{ $item['label'] }}
                    </a>
                    <svg class="w-3 h-3 text-gray-400 mx-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/>
                    </svg>
                @else
                    <span class="inline-flex items-center text-sm font-medium text-gray-500">
                        <i class="bi {{ $item['icon'] }} mr-2 text-gray-400"></i>
                        {{ $item['label'] }}
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>