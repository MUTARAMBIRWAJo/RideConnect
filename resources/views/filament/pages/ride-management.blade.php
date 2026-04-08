<x-filament-panels::page>
    <div class="space-y-4" wire:poll.20s>
        <x-filament::section>
            <x-slot name="heading">Ride Management</x-slot>
            <x-slot name="description">Search, monitor, and intervene in ride operations in real time.</x-slot>
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
