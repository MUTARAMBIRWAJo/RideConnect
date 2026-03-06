{{-- resources/views/filament/pages/bi-dashboard.blade.php --}}
<x-filament-panels::page>
    {{-- Header widgets: Revenue + Commission stats --}}
    @if ($this->getHeaderWidgets())
        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
            :columns="$this->getHeaderWidgetsColumns()"
        />
    @endif

    {{-- Page content (empty placeholder) --}}
    <div></div>

    {{-- Footer widgets: Charts + Leaderboard --}}
    @if ($this->getFooterWidgets())
        <x-filament-widgets::widgets
            :widgets="$this->getFooterWidgets()"
            :columns="$this->getFooterWidgetsColumns()"
        />
    @endif
</x-filament-panels::page>
