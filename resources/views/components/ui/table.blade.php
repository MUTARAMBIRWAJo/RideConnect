<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    @if($title)
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>
                <div class="flex items-center space-x-2">
                    {{ $actions ?? '' }}
                </div>
            </div>
        </div>
    @endif

    @if($search || $filters)
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @if($search)
                    <div class="md:col-span-2">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="bi bi-search text-gray-400"></i>
                            </div>
                            <input type="text" 
                                   wire:model.debounce.300ms="search" 
                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="{{ $searchPlaceholder ?? 'Search...' }}">
                        </div>
                    </div>
                @endif
                {{ $filters ?? '' }}
            </div>
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    @foreach($headers as $header)
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @if($rows->isEmpty())
                    <tr>
                        <td colspan="{{ count($headers) }}" class="px-6 py-4 text-center text-gray-500">
                            No data available
                        </td>
                    </tr>
                @else
                    @foreach($rows as $row)
                        <tr class="hover:bg-gray-50">
                            @foreach($columns as $column)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $row->$column }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>

    @if($rows->hasPages())
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
            {{ $rows->links() }}
        </div>
    @endif
</div>