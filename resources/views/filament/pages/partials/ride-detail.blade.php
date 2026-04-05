<div class="space-y-2 text-sm">
    <div><span class="font-semibold">Ride ID:</span> #{{ $ride->id }}</div>
    <div><span class="font-semibold">Status:</span> {{ $ride->status }}</div>
    <div><span class="font-semibold">Origin:</span> {{ $ride->origin_address }}</div>
    <div><span class="font-semibold">Destination:</span> {{ $ride->destination_address }}</div>
    <div><span class="font-semibold">Departure:</span> {{ optional($ride->departure_time)->toDateTimeString() }}</div>
    <div><span class="font-semibold">Created:</span> {{ optional($ride->created_at)->toDateTimeString() }}</div>
</div>
