<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Header Section -->
        <x-filament::section class="overflow-hidden border-0 bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-blue-100">Officer Operations</p>
                    <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Create Booking or Trip</h1>
                    <p class="mt-2 max-w-2xl text-sm text-blue-100 sm:text-base">
                        Create transportation requests for passengers by selecting corridors and locations.
                    </p>
                </div>
            </div>
        </x-filament::section>

        <!-- Main Form Section -->
        <x-filament::section>
            <form id="bookingTripForm" class="space-y-6">
                @csrf

                <!-- Type Selection -->
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="flex items-center gap-3 rounded-lg border-2 border-gray-200 p-4 cursor-pointer transition hover:border-blue-500 hover:bg-blue-50"
                           onclick="selectType('booking')">
                        <input type="radio" name="type" value="booking" id="typeBooking" class="h-5 w-5" checked>
                        <div>
                            <p class="font-semibold text-gray-900">Booking</p>
                            <p class="text-sm text-gray-600">Passenger request - awaits driver acceptance</p>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 rounded-lg border-2 border-gray-200 p-4 cursor-pointer transition hover:border-green-500 hover:bg-green-50"
                           onclick="selectType('trip')">
                        <input type="radio" name="type" value="trip" id="typeTrip" class="h-5 w-5">
                        <div>
                            <p class="font-semibold text-gray-900">Trip</p>
                            <p class="text-sm text-gray-600">Direct assignment - requires driver selection</p>
                        </div>
                    </label>
                </div>

                <!-- Passenger Section -->
                <div class="grid gap-4 md:grid-cols-2">
                    <!-- Passenger Search/Select (Only for Trip) -->
                    <div id="passengerSection" class="hidden">
                        <label class="block text-sm font-semibold text-gray-900 mb-2">Select Passenger (Mobile User)</label>
                        <div class="flex gap-2">
                            <div class="flex-1 relative">
                                <input type="text" 
                                       id="passengerSearch" 
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm placeholder-gray-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                       placeholder="Search by name, email, or phone..."
                                       autocomplete="off">
                                <div id="passengerSearchResults" class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-lg shadow-lg max-h-48 overflow-y-auto hidden"></div>
                            </div>
                        </div>
                        <input type="hidden" id="passengerId" name="passenger_id">
                        <div id="selectedPassenger" class="mt-2 p-2 bg-blue-50 rounded-lg hidden">
                            <p class="text-sm font-semibold text-blue-900"><span id="selectedPassengerName"></span></p>
                            <p class="text-xs text-blue-700"><span id="selectedPassengerEmail"></span></p>
                        </div>
                    </div>

                    <!-- Driver Selection (Trip only) -->
                    <div id="driverSection" class="hidden">
                        <label class="block text-sm font-semibold text-gray-900 mb-2">Select Driver</label>
                        <select id="driverId" 
                                name="driver_id"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200">
                            <option value="">-- Select a driver --</option>
                            <!-- Populated by JS -->
                        </select>
                    </div>

                    <!-- WebUser Selection (Only for Booking) -->
                    <div id="webuserSection">
                        <label class="block text-sm font-semibold text-gray-900 mb-2">Web User (Optional)</label>
                        <select id="userId" 
                                name="user_id"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                            <option value="">-- Select web user (optional) --</option>
                            <!-- Populated by JS -->
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Leave empty for passenger-less booking management</p>
                    </div>
                </div>

                <!-- Location Section -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-900">Locations</h3>
                    
                    <div class="grid gap-4 md:grid-cols-2">
                        <!-- Corridor/Route Selection -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-900 mb-2">Corridor/Route</label>
                            <input type="text" 
                                   id="corridorSearch" 
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm placeholder-gray-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                   placeholder="Search corridors..."
                                   autocomplete="off">
                            <div id="corridorSearchResults" class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-lg shadow-lg max-h-48 overflow-y-auto hidden"></div>
                            <input type="hidden" id="corridorId" name="corridor_id">
                        </div>

                        <!-- Quick Zone Selection -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-900 mb-2">Quick Zone Select</label>
                            <select id="zoneSelect" 
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option value="">-- Select a zone --</option>
                                <!-- Populated by JS -->
                            </select>
                        </div>
                    </div>

                    <!-- Pickup Location -->
                    <div class="space-y-2">
                        <h4 class="font-semibold text-gray-900">Pickup Location</h4>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm text-gray-700 mb-1">Search Location</label>
                                <input type="text" 
                                       id="pickupSearch" 
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm placeholder-gray-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                       placeholder="Search pickup location..."
                                       autocomplete="off">
                                <div id="pickupSearchResults" class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-lg shadow-lg max-h-40 overflow-y-auto hidden"></div>
                            </div>
                            <div id="pickupMapContainer" class="relative h-48 rounded-lg border border-gray-300 overflow-hidden bg-gray-100">
                                <div id="pickupMap" class="w-full h-full"></div>
                                <p class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">Click on map to select pickup</p>
                            </div>
                        </div>
                        <div class="grid gap-2 md:grid-cols-3">
                            <input type="text" 
                                   id="pickupAddress" 
                                   name="pickup_address" 
                                   class="rounded-lg border border-gray-300 px-3 py-2 text-sm" 
                                   placeholder="Address" 
                                   readonly>
                            <input type="hidden" id="pickupLat" name="pickup_lat">
                            <input type="hidden" id="pickupLng" name="pickup_lng">
                            <input type="number" 
                                   id="pickupLatDisplay" 
                                   class="rounded-lg border border-gray-300 px-3 py-2 text-sm" 
                                   placeholder="Latitude" 
                                   step="0.00001" 
                                   readonly>
                            <input type="number" 
                                   id="pickupLngDisplay" 
                                   class="rounded-lg border border-gray-300 px-3 py-2 text-sm" 
                                   placeholder="Longitude" 
                                   step="0.00001" 
                                   readonly>
                        </div>
                    </div>

                    <!-- Dropoff Location -->
                    <div class="space-y-2">
                        <h4 class="font-semibold text-gray-900">Dropoff Location</h4>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm text-gray-700 mb-1">Search Location</label>
                                <input type="text" 
                                       id="dropoffSearch" 
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm placeholder-gray-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                       placeholder="Search dropoff location..."
                                       autocomplete="off">
                                <div id="dropoffSearchResults" class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-lg shadow-lg max-h-40 overflow-y-auto hidden"></div>
                            </div>
                            <div id="dropoffMapContainer" class="relative h-48 rounded-lg border border-gray-300 overflow-hidden bg-gray-100">
                                <div id="dropoffMap" class="w-full h-full"></div>
                                <p class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">Click on map to select dropoff</p>
                            </div>
                        </div>
                        <div class="grid gap-2 md:grid-cols-3">
                            <input type="text" 
                                   id="dropoffAddress" 
                                   name="dropoff_address" 
                                   class="rounded-lg border border-gray-300 px-3 py-2 text-sm" 
                                   placeholder="Address" 
                                   readonly>
                            <input type="hidden" id="dropoffLat" name="dropoff_lat">
                            <input type="hidden" id="dropoffLng" name="dropoff_lng">
                            <input type="number" 
                                   id="dropoffLatDisplay" 
                                   class="rounded-lg border border-gray-300 px-3 py-2 text-sm" 
                                   placeholder="Latitude" 
                                   step="0.00001" 
                                   readonly>
                            <input type="number" 
                                   id="dropoffLngDisplay" 
                                   class="rounded-lg border border-gray-300 px-3 py-2 text-sm" 
                                   placeholder="Longitude" 
                                   step="0.00001" 
                                   readonly>
                        </div>
                    </div>
                </div>

                <!-- Additional Details -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-2">Seats Booked</label>
                        <input type="number" 
                               name="seats_booked" 
                               min="1" 
                               max="6" 
                               value="1"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                               required>
                    </div>

                    <div id="tripFareSection" class="hidden">
                        <label class="block text-sm font-semibold text-gray-900 mb-2">Fare (RWF)</label>
                        <input type="number" 
                               id="tripFare"
                               name="fare" 
                               min="0" 
                               step="50"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200">
                    </div>
                </div>

                <!-- Special Requests -->
                <div>
                    <label class="block text-sm font-semibold text-gray-900 mb-2">Special Requests</label>
                    <textarea name="special_requests" 
                              rows="3"
                              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                              placeholder="Any special requests from the passenger..."></textarea>
                </div>

                <!-- Submit Button -->
                <div class="flex gap-2 pt-4">
                    <button type="submit" 
                            id="submitBtn"
                            class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-6 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                        Create Booking
                    </button>
                    <a href="{{ route('filament.admin.pages.officer-dashboard-v2') }}" 
                       class="inline-flex items-center justify-center rounded-lg bg-gray-300 px-6 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-400">
                        Cancel
                    </a>
                </div>
            </form>
        </x-filament::section>
    </div>

    <!-- New Passenger Modal -->
    <div id="newPassengerModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 hidden">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Create New Passenger</h3>
            <form id="newPassengerForm" class="space-y-4">
                @csrf
                <div class="grid gap-4 md:grid-cols-2">
                    <input type="text" 
                           name="first_name" 
                           placeholder="First Name"
                           class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                           required>
                    <input type="text" 
                           name="last_name" 
                           placeholder="Last Name"
                           class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                           required>
                </div>
                <input type="email" 
                       name="email" 
                       placeholder="Email"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                       required>
                <input type="tel" 
                       name="phone" 
                       placeholder="Phone"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                       required>
                <div class="flex gap-2">
                    <button type="button" 
                            onclick="closeNewPassengerModal()"
                            class="flex-1 rounded-lg bg-gray-300 px-4 py-2 text-sm font-semibold hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Create
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Styles -->
    <style>
        #pickupMap, #dropoffMap {
            background: #f0f0f0;
        }
        .location-result {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.875rem;
        }
        .location-result:hover {
            background-color: #f3f4f6;
        }
        .location-result-title {
            font-weight: 500;
            color: #111827;
        }
        .location-result-type {
            font-size: 0.75rem;
            color: #6b7280;
        }
    </style>

    <!-- Scripts -->
    <script>
        const apiBase = '{{ config('app.url') }}/api';

        // Form state
        let bookingType = 'booking';
        let pickupCoords = null;
        let dropoffCoords = null;

        // Passenger search
        document.getElementById('passengerSearch')?.addEventListener('input', debounce(async (e) => {
            const search = e.target.value;
            if (search.length < 2) {
                document.getElementById('passengerSearchResults').classList.add('hidden');
                return;
            }

            try {
                const res = await fetch(`${apiBase}/officer/passengers?search=${encodeURIComponent(search)}`, {
                    headers: { 'Authorization': `Bearer {{ auth()->user()->currentAccessToken()?->plainTextToken }}` }
                });
                const data = await res.json();
                displayPassengerResults(data.data || []);
            } catch (err) {
                console.error('Passenger search error:', err);
            }
        }, 300));

        function displayPassengerResults(passengers) {
            const container = document.getElementById('passengerSearchResults');
            if (passengers.length === 0) {
                container.classList.add('hidden');
                return;
            }

            container.innerHTML = passengers.map(p => `
                <div class="location-result" onclick="selectPassenger(${p.id}, '${p.name}', '${p.email}')">
                    <div class="location-result-title">${p.name}</div>
                    <div class="location-result-type">${p.email} • ${p.phone}</div>
                </div>
            `).join('');
            container.classList.remove('hidden');
        }

        function selectPassenger(id, name, email) {
            document.getElementById('passengerId').value = id;
            document.getElementById('passengerSearch').value = name;
            document.getElementById('selectedPassengerName').textContent = name;
            document.getElementById('selectedPassengerEmail').textContent = email;
            document.getElementById('selectedPassenger').classList.remove('hidden');
            document.getElementById('passengerSearchResults').classList.add('hidden');
        }

        // Location search
        ['pickup', 'dropoff'].forEach(type => {
            document.getElementById(`${type}Search`)?.addEventListener('input', debounce(async (e) => {
                const search = e.target.value;
                if (search.length < 2) {
                    document.getElementById(`${type}SearchResults`).classList.add('hidden');
                    return;
                }

                try {
                    const res = await fetch(`${apiBase}/officer/locations/search?q=${encodeURIComponent(search)}`, {
                        headers: { 'Authorization': `Bearer {{ auth()->user()->currentAccessToken()?->plainTextToken }}` }
                    });
                    const data = await res.json();
                    displayLocationResults(type, data.data || []);
                } catch (err) {
                    console.error(`${type} search error:`, err);
                }
            }, 300));
        });

        function displayLocationResults(type, locations) {
            const container = document.getElementById(`${type}SearchResults`);
            if (locations.length === 0) {
                container.classList.add('hidden');
                return;
            }

            container.innerHTML = locations.map(loc => `
                <div class="location-result" onclick="selectLocation('${type}', ${loc.lat}, ${loc.lng}, '${loc.name}')">
                    <div class="location-result-title">${loc.name}</div>
                    <div class="location-result-type">${loc.type}</div>
                </div>
            `).join('');
            container.classList.remove('hidden');
        }

        function selectLocation(type, lat, lng, address) {
            const latField = document.getElementById(`${type}Lat`);
            const lngField = document.getElementById(`${type}Lng`);
            const addressField = document.getElementById(`${type}Address`);
            const latDisplay = document.getElementById(`${type}LatDisplay`);
            const lngDisplay = document.getElementById(`${type}LngDisplay`);

            latField.value = lat;
            lngField.value = lng;
            addressField.value = address;
            latDisplay.value = lat;
            lngDisplay.value = lng;

            document.getElementById(`${type}SearchResults`).classList.add('hidden');
            document.getElementById(`${type}Search`).value = address;

            if (type === 'pickup') {
                pickupCoords = { lat, lng };
            } else {
                dropoffCoords = { lat, lng };
            }
        }

        // Type selection
        function selectType(type) {
            bookingType = type;
            
            // Show/hide sections based on type
            document.getElementById('passengerSection').classList.toggle('hidden', type === 'booking');
            document.getElementById('driverSection').classList.toggle('hidden', type === 'booking');
            document.getElementById('webuserSection').classList.toggle('hidden', type === 'trip');
            document.getElementById('tripFareSection').classList.toggle('hidden', type === 'booking');
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.textContent = type === 'booking' ? 'Create Booking' : 'Create Trip';
            submitBtn.className = type === 'booking' ? 
                'inline-flex items-center justify-center rounded-lg bg-blue-600 px-6 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50' :
                'inline-flex items-center justify-center rounded-lg bg-green-600 px-6 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50';
            
            // Update requirements
            document.getElementById('driverId').required = type === 'trip';
            document.getElementById('tripFare').required = type === 'trip';
            document.getElementById('passengerId').required = type === 'trip';
        }

        // Modal functions
        function openNewPassengerModal() {
            document.getElementById('newPassengerModal').classList.remove('hidden');
        }

        function closeNewPassengerModal() {
            document.getElementById('newPassengerModal').classList.add('hidden');
        }

        // New passenger form
        document.getElementById('newPassengerForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            try {
                const res = await fetch(`${apiBase}/officer/passengers`, {
                    method: 'POST',
                    headers: { 
                        'Authorization': `Bearer {{ auth()->user()->currentAccessToken()?->plainTextToken }}`,
                        'Accept': 'application/json',
                    },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    selectPassenger(data.data.id, data.data.name, data.data.email);
                    closeNewPassengerModal();
                    e.target.reset();
                }
            } catch (err) {
                console.error('Passenger creation error:', err);
            }
        });

        // Form submission
        document.getElementById('bookingTripForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            try {
                const endpoint = bookingType === 'booking' ? 
                    `${apiBase}/officer/bookings` : 
                    `${apiBase}/officer/trips`;
                
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 
                        'Authorization': `Bearer {{ auth()->user()->currentAccessToken()?->plainTextToken }}`,
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    },
                    body: formData
                });

                const data = await res.json();
                if (data.success) {
                    alert(`${bookingType.charAt(0).toUpperCase() + bookingType.slice(1)} created successfully!`);
                    window.location.href = '{{ route('filament.admin.pages.officer-dashboard-v2') }}';
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            } catch (err) {
                console.error('Submission error:', err);
                alert('Error creating ' + bookingType);
            }
        });

        // Utility
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Initialize
        selectType('booking');
    </script>
</x-filament-panels::page>
