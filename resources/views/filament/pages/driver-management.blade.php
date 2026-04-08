<x-filament-panels::page>
    <div class="space-y-4" wire:poll.30s>
        <x-filament::section>
            <x-slot name="heading">Driver Management</x-slot>
            <x-slot name="description">Track driver quality, approval state, and operational readiness.</x-slot>
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
