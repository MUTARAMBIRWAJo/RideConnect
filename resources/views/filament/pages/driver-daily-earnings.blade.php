<x-filament-panels::page>
    <div wire:poll.30s>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
