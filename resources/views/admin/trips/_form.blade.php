{{-- resources/views/admin/trips/_form.blade.php --}}
{{-- $trip may be null (create) or a Trip model (edit) --}}

@php $editing = isset($trip); @endphp

{{-- ════════════════════════════════════════════════════════════
     SECTION 1: LOCATION — mirrors Flutter BookRideScreen fields
     ════════════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
  <h2 class="text-lg font-semibold text-gray-800 border-b pb-2">📍 Location Details</h2>
  <p class="text-xs text-gray-400">These fields match the pickup/dropoff fields in the Flutter mobile app.</p>

  {{-- pickup_location — Flutter: _pickupController.text --}}
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        Pickup Location <span class="text-red-500">*</span>
        <span class="text-xs text-gray-400 font-normal">(pickup_location)</span>
      </label>
      <input type="text" name="pickup_location"
             value="{{ old('pickup_location', $trip->pickup_location ?? '') }}"
             placeholder="e.g. Kigali City Tower, Nyarugenge"
             required minlength="3" maxlength="500"
             class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500 @error('pickup_location') border-red-500 @enderror">
      @error('pickup_location')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
      @enderror
    </div>

    {{-- dropoff_location — Flutter: _dropoffController.text --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        Dropoff Location <span class="text-red-500">*</span>
        <span class="text-xs text-gray-400 font-normal">(dropoff_location)</span>
      </label>
      <input type="text" name="dropoff_location"
             value="{{ old('dropoff_location', $trip->dropoff_location ?? '') }}"
             placeholder="e.g. Nyabugogo Bus Station"
             required minlength="3" maxlength="500"
             class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500 @error('dropoff_location') border-red-500 @enderror">
      @error('dropoff_location')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
      @enderror
    </div>
  </div>

  {{-- Coordinates — Flutter sets these from map pin tap --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div>
      <label class="block text-xs font-medium text-gray-600 mb-1">
        Pickup Lat <span class="text-red-500">*</span>
        <span class="text-gray-400">(pickup_lat)</span>
      </label>
      <input type="number" name="pickup_lat" step="0.0000001"
             value="{{ old('pickup_lat', $trip->pickup_lat ?? '') }}"
             placeholder="-1.9441000" required
             class="w-full border rounded-lg px-3 py-2 text-sm @error('pickup_lat') border-red-500 @enderror">
      @error('pickup_lat') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
      <label class="block text-xs font-medium text-gray-600 mb-1">
        Pickup Lng <span class="text-red-500">*</span>
        <span class="text-gray-400">(pickup_lng)</span>
      </label>
      <input type="number" name="pickup_lng" step="0.0000001"
             value="{{ old('pickup_lng', $trip->pickup_lng ?? '') }}"
             placeholder="30.0619000" required
             class="w-full border rounded-lg px-3 py-2 text-sm @error('pickup_lng') border-red-500 @enderror">
      @error('pickup_lng') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
      <label class="block text-xs font-medium text-gray-600 mb-1">
        Dropoff Lat <span class="text-red-500">*</span>
        <span class="text-gray-400">(dropoff_lat)</span>
      </label>
      <input type="number" name="dropoff_lat" step="0.0000001"
             value="{{ old('dropoff_lat', $trip->dropoff_lat ?? '') }}"
             placeholder="-1.9355000" required
             class="w-full border rounded-lg px-3 py-2 text-sm @error('dropoff_lat') border-red-500 @enderror">
      @error('dropoff_lat') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
      <label class="block text-xs font-medium text-gray-600 mb-1">
        Dropoff Lng <span class="text-red-500">*</span>
        <span class="text-gray-400">(dropoff_lng)</span>
      </label>
      <input type="number" name="dropoff_lng" step="0.0000001"
             value="{{ old('dropoff_lng', $trip->dropoff_lng ?? '') }}"
             placeholder="30.0445000" required
             class="w-full border rounded-lg px-3 py-2 text-sm @error('dropoff_lng') border-red-500 @enderror">
      @error('dropoff_lng') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
  </div>

  {{-- Optional place names & zones --}}
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="block text-xs font-medium text-gray-600 mb-1">
        Pickup Place Name <span class="text-gray-400">(pickup_place_name)</span>
      </label>
      <input type="text" name="pickup_place_name" maxlength="255"
             value="{{ old('pickup_place_name', $trip->pickup_place_name ?? '') }}"
             placeholder="Optional — friendly name"
             class="w-full border rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
      <label class="block text-xs font-medium text-gray-600 mb-1">
        Dropoff Place Name <span class="text-gray-400">(dropoff_place_name)</span>
      </label>
      <input type="text" name="dropoff_place_name" maxlength="255"
             value="{{ old('dropoff_place_name', $trip->dropoff_place_name ?? '') }}"
             placeholder="Optional — friendly name"
             class="w-full border rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
      <label class="block text-xs font-medium text-gray-600 mb-1">
        Pickup Zone <span class="text-gray-400">(pickup_zone, max 64)</span>
      </label>
      <input type="text" name="pickup_zone" maxlength="64"
             value="{{ old('pickup_zone', $trip->pickup_zone ?? '') }}"
             placeholder="e.g. nyarugenge_central"
             class="w-full border rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
      <label class="block text-xs font-medium text-gray-600 mb-1">
        Dropoff Zone <span class="text-gray-400">(dropoff_zone, max 64)</span>
      </label>
      <input type="text" name="dropoff_zone" maxlength="64"
             value="{{ old('dropoff_zone', $trip->dropoff_zone ?? '') }}"
             placeholder="e.g. nyabugogo_terminal"
             class="w-full border rounded-lg px-3 py-2 text-sm">
    </div>
  </div>
</div>

{{-- ════════════════════════════════════════════════════════════
     SECTION 2: TRIP TYPE — mirrors Flutter transport_type chips
     ════════════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
  <h2 class="text-lg font-semibold text-gray-800 border-b pb-2">🚗 Transport & Payment</h2>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

    {{-- transport_type — MUST match Flutter chip values exactly --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">
        Transport Type <span class="text-red-500">*</span>
        <span class="text-xs text-gray-400 font-normal">(transport_type)</span>
      </label>
      <div class="flex gap-3">
        @foreach($transportOptions as $val => $label)
          <label class="flex-1">
            <input type="radio" name="transport_type" value="{{ $val }}"
                   {{ old('transport_type', $trip->transport_type ?? 'moto') === $val ? 'checked' : '' }}
                   class="sr-only peer" required>
            <div class="border-2 rounded-xl py-3 text-center text-sm font-medium cursor-pointer
                        peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-700
                        hover:border-gray-400 transition select-none">
              {{ $label }}
            </div>
          </label>
        @endforeach
      </div>
      @error('transport_type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- payment_method — MUST match Flutter payment options --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">
        Payment Method <span class="text-red-500">*</span>
        <span class="text-xs text-gray-400 font-normal">(payment_method)</span>
      </label>
      <div class="flex gap-3">
        @foreach($paymentOptions as $val => $label)
          <label class="flex-1">
            <input type="radio" name="payment_method" value="{{ $val }}"
                   {{ old('payment_method', 'cash') === $val ? 'checked' : '' }}
                   class="sr-only peer" required>
            <div class="border-2 rounded-xl py-3 text-center text-sm font-medium cursor-pointer
                        peer-checked:border-green-600 peer-checked:bg-green-50 peer-checked:text-green-700
                        hover:border-gray-400 transition select-none">
              {{ $label }}
            </div>
          </label>
        @endforeach
      </div>
      @error('payment_method') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
  </div>

  {{-- fare — admin sets this manually; Flutter gets it from the API response --}}
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        Estimated Fare (RWF) <span class="text-red-500">*</span>
        <span class="text-xs text-gray-400 font-normal">(fare)</span>
      </label>
      <div class="relative">
        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-sm">RWF</span>
        <input type="number" name="fare" step="50" min="0"
               value="{{ old('fare', isset($trip) ? (int)$trip->fare : '') }}"
               placeholder="1500" required
               class="w-full border rounded-lg pl-12 pr-3 py-2 text-sm @error('fare') border-red-500 @enderror">
      </div>
      @error('fare') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- passenger_id — Flutter sends mobile_users.id --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        Passenger <span class="text-red-500">*</span>
        <span class="text-xs text-gray-400 font-normal">(passenger_id → mobile_users.id)</span>
      </label>
      <select name="passenger_id" required
              class="w-full border rounded-lg px-3 py-2 text-sm @error('passenger_id') border-red-500 @enderror">
        <option value="">Select passenger...</option>
        @foreach($passengers as $p)
          <option value="{{ $p->id }}"
                  {{ old('passenger_id', $trip->passenger_id ?? '') == $p->id ? 'selected' : '' }}>
            {{ $p->first_name }} {{ $p->last_name }} — {{ $p->phone }}
          </option>
        @endforeach
      </select>
      @error('passenger_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
  </div>
</div>

{{-- ════════════════════════════════════════════════════════════
     SECTION 3: ADMIN CONTROLS (not in Flutter — admin-only)
     ════════════════════════════════════════════════════════════ --}}
@if($editing)
<div class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
  <h2 class="text-lg font-semibold text-gray-800 border-b pb-2">
    ⚙️ Admin Controls
    <span class="text-xs font-normal text-gray-400 ml-2">Not editable from Flutter — admin only</span>
  </h2>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

    {{-- status — MUST match Flutter status lifecycle enum --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        Status <span class="text-xs text-gray-400">(status)</span>
      </label>
      <select name="status" required
              class="w-full border rounded-lg px-3 py-2 text-sm @error('status') border-red-500 @enderror">
        @foreach($statusOptions as $s)
          <option value="{{ $s }}" {{ old('status', $trip->status) === $s ? 'selected' : '' }}>
            {{ Str::title(str_replace('_', ' ', $s)) }}
          </option>
        @endforeach
      </select>
      @error('status') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- assignment_status --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        Assignment Status <span class="text-xs text-gray-400">(assignment_status)</span>
      </label>
      <select name="assignment_status" required
              class="w-full border rounded-lg px-3 py-2 text-sm">
        @foreach($assignmentStatuses as $as)
          <option value="{{ $as }}" {{ old('assignment_status', $trip->assignment_status) === $as ? 'selected' : '' }}>
            {{ Str::title(str_replace('_', ' ', $as)) }}
          </option>
        @endforeach
      </select>
    </div>

    {{-- payment_status --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        Payment Status <span class="text-xs text-gray-400">(payment_status)</span>
      </label>
      <select name="payment_status" required
              class="w-full border rounded-lg px-3 py-2 text-sm">
        @foreach($paymentStatuses as $ps)
          <option value="{{ $ps }}" {{ old('payment_status', $trip->payment_status) === $ps ? 'selected' : '' }}>
            {{ Str::title($ps) }}
          </option>
        @endforeach
      </select>
    </div>

    {{-- driver assignment --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">
        Assign Driver <span class="text-xs text-gray-400">(driver_id)</span>
      </label>
      <select name="driver_id" class="w-full border rounded-lg px-3 py-2 text-sm">
        <option value="">Unassigned</option>
        @foreach($drivers as $d)
          <option value="{{ $d->id }}"
                  {{ old('driver_id', $trip->driver_id) == $d->id ? 'selected' : '' }}>
            {{ $d->user->first_name ?? '' }} {{ $d->user->last_name ?? '' }}
            — {{ $d->license_plate }}
            (⭐ {{ number_format($d->rating, 1) }})
          </option>
        @endforeach
      </select>
    </div>

    {{-- admin_completion_reason --}}
    <div class="md:col-span-2">
      <label class="block text-sm font-medium text-gray-700 mb-1">
        Admin Completion Reason <span class="text-xs text-gray-400">(admin_completion_reason)</span>
      </label>
      <input type="text" name="admin_completion_reason" maxlength="500"
             value="{{ old('admin_completion_reason', $trip->admin_completion_reason ?? '') }}"
             placeholder="Required when admin-completing a trip..."
             class="w-full border rounded-lg px-3 py-2 text-sm">
    </div>
  </div>
</div>
@endif
