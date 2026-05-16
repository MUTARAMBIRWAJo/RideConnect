@php
    /**
     * location-input — exclusive search-or-map location picker for Filament.
     *
     * @var array  $viewData keys:
     *   addressField   hidden text input that stores the human-readable place name  (required)
     *   latField       hidden numeric input that stores latitude                        (required)
     *   lngField       hidden numeric input that stores longitude                      (required)
     *   placeNameField hidden text input that stores the Google Places place name       (optional)
     *   label          field label visible to the user                                 (default "Location")
     *   placeholder    search placeholder text                                         (default "Search for a place…")
     *   height         map height in px                                                (default 260)
     *   centerLat      initial map centre latitude                                     (default -1.9403)
     *   centerLng      initial map centre longitude                                    (default 29.8739)
     *   zoom           initial map zoom                                                 (default 12)
     *
     * Mutual exclusion rule:
     *   - Mode "search"  → search bar enabled, map interaction disabled
     *   - Mode "map"     → map tap/drag enabled, search bar disabled
     *   Only one mode is active at any time.
     */
    $addressField    = $addressField    ?? 'pickup_address';
    $latField        = $latField        ?? 'pickup_lat';
    $lngField        = $lngField        ?? 'pickup_lng';
    $placeNameField  = $placeNameField  ?? 'pickup_place_name';
    $label           = $label           ?? 'Location';
    $placeholder     = $placeholder     ?? 'Search for a place…';
    $height          = (int) ($height       ?? 260);
    $centerLat       = (float) ($centerLat    ?? -1.9403);
    $centerLng       = (float) ($centerLng    ?? 29.8739);
    $zoom            = (int) ($zoom         ?? 12);
    $modeLabelSearch = $modeLabelSearch ?? 'Search by name';
    $modeLabelMap    = $modeLabelMap    ?? 'Pick from map';
    $instanceId      = 'loc-' . md5(($addressField ?? 'loc') . '|' . ($latField ?? 'lat') . '|' . ($lngField ?? 'lng'));
    $apiKey          = config('laramaps.api_key');
@endphp

@if($apiKey)
    <div class="fi-fo-field-wrp space-y-2"
         x-data="locationInput({
             instanceId: @js($instanceId),
             addressField: @js($addressField),
             latField: @js($latField),
             lngField: @js($lngField),
             placeNameField: @js($placeNameField),
             placeholder: @js($placeholder),
             height: {{ $height }},
             centerLat: {{ $centerLat }},
             centerLng: {{ $centerLng }},
             zoom: {{ $zoom }},
             modeLabelSearch: @js($modeLabelSearch),
             modeLabelMap: @js($modeLabelMap),
         })"
         x-init="init()"
         wire:ignore>
        <div class="flex items-center justify-between">
            <label class="text-sm font-medium text-gray-700">{{ $label }}</label>

            {{-- Exclusive mode toggle --}}
            <div class="flex rounded-md shadow-sm" role="group">
                <button type="button"
                        x-bind:class="mode === 'search'
                            ? 'z-10 inline-flex items-center rounded-l-md border border-blue-600 bg-blue-600 px-3 py-1.5 text-xs font-medium text-white focus:z-20 focus:border-blue-700 focus:ring-2 focus:ring-blue-500'
                            : 'inline-flex items-center rounded-l-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 focus:z-20'
                        "
                        x-on:click="setMode('search')">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    {{ $modeLabelSearch }}
                </button>
                <button type="button"
                        x-bind:class="mode === 'map'
                            ? 'z-10 inline-flex items-center rounded-r-md border border-blue-600 bg-blue-600 px-3 py-1.5 text-xs font-medium text-white focus:z-20 focus:border-blue-700 focus:ring-2 focus:ring-blue-500'
                            : 'inline-flex items-center rounded-r-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 -ml-px focus:z-20'
                        "
                        x-on:click="setMode('map')">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    {{ $modeLabelMap }}
                </button>
            </div>
        </div>

        {{-- Status / helper text --}}
        <p class="text-xs text-gray-500 min-h-[1.1em]" x-text="statusMessage"></p>

        {{-- ================================================================
             MODE A: SEARCH BY PLACE NAME
             Search bar enabled — map interaction disabled
             ================================================================ --}}
        <div x-show="mode === 'search'"
             x-transition
             class="space-y-3"
             x-cloak>
            <div class="relative">
                <input
                    type="text"
                    id="{{ $instanceId }}-search-input"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    :placeholder="placeholder"
                    autocomplete="off"
                    :disabled="mode !== 'search'"
                    x-on:input.debounce.300ms="onSearchInput($el.value)"
                    x-on:focus="searchOpen = true"
                    x-on:blur="setTimeout(() => searchOpen = false, 200)"
                />
                <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </span>

                {{-- Autocomplete dropdown --}}
                <div x-show="searchOpen && (searchResults.length > 0 || searchQuery.length >= 2)"
                     x-transition
                     x-cloak
                     class="absolute z-50 mt-1 w-full rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 max-h-72 overflow-y-auto">
                    <template x-for="result in searchResults" :key="result.place_id">
                        <button type="button"
                                class="block w-full cursor-pointer px-4 py-2.5 text-left text-sm text-gray-900 hover:bg-blue-50 focus:bg-blue-50 focus:outline-none"
                                x-on:mousedown.prevent
                                x-on:click="selectPlace(result)">
                            <span x-text="result.main_text" class="font-medium"></span>
                            <span x-text="result.secondary_text" class="block text-xs text-gray-500"></span>
                        </button>
                    </template>
                    <div x-show="searchQuery.length >= 2 && searchResults.length === 0"
                         class="px-4 py-3 text-xs text-gray-400 italic">
                        No results found.
                    </div>
                </div>
            </div>

            {{-- Disabled map (visual only) when in search mode --}}
            <div id="{{ $instanceId }}-map"
                 class="h-[{{ $height }}px] w-full rounded-lg border border-gray-200 opacity-40 pointer-events-none grayscale"
                 aria-hidden="true">
            </div>
        </div>

        {{-- ================================================================
             MODE B: PICK FROM MAP
             Interactive map enabled — search input disabled
             ================================================================ --}}
        <div x-show="mode === 'map'"
             x-transition
             class="space-y-3"
             x-cloak>
            <p class="text-xs text-gray-400 italic">Search temporarily disabled. Tap the map to set a location.</p>

            <div class="flex items-center gap-2">
                <button type="button"
                        x-on:click="useCurrentLocation"
                        class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Use my location
                </button>
                <span class="text-xs text-gray-400" x-text="mapPlaceLabel">Click map to set location</span>
            </div>

            <div id="{{ $instanceId }}-map"
                 class="h-[{{ $height }}px] w-full rounded-lg border border-gray-200"
                 :class="mode === 'map' ? '' : 'opacity-40 pointer-events-none grayscale'">
            </div>
        </div>

        <script>
            (function () {
                function locationInput(config) {
                    return {
                        instanceId: config.instanceId,
                        addressField: config.addressField,
                        latField: config.latField,
                        lngField: config.lngField,
                        placeNameField: config.placeNameField,
                        placeholder: config.placeholder || 'Search for a place…',
                        height: config.height || 260,
                        centerLat: config.centerLat ?? -1.9403,
                        centerLng: config.centerLng ?? 29.8739,
                        zoom: config.zoom ?? 12,
                        modeLabelSearch: config.modeLabelSearch || 'Search by name',
                        modeLabelMap: config.modeLabelMap || 'Pick from map',
                        mode: 'search',           // 'search' | 'map'
                        statusMessage: 'Loading location assistant…',
                        mapPlaceLabel: 'Tap the map to set a location',
                        searchOpen: false,
                        searchQuery: '',
                        searchResults: [],
                        map: null,
                        marker: null,
                        autocomplete: null,
                        searchDebounce: null,

                        init() {
                            if (! window.google || ! window.google.maps) {
                                this.statusMessage = 'Google Maps library not available.';
                                return;
                            }

                            this.setupMap();
                            this.setupAutocomplete();
                        },

                        // ========================================================
                        //   MODE SWITCHING  (mutual exclusion)
                        // ========================================================
                        setMode(newMode) {
                            this.mode = newMode;

                            if (newMode === 'search') {
                                // Disable map interaction
                                if (this.marker) this.marker.setDraggable(false);
                                if (this.map) this.map.setOptions({ draggable: false, clickableIcons: false });
                                this.statusMessage = 'Type a place name to search.';
                            } else {
                                // Disable search — clear autocomplete to prevent stale suggestions
                                if (this.autocomplete) {
                                    this.autocomplete.unbindAll();
                                    this.autocomplete = null;
                                }
                                this.searchOpen = false;
                                this.searchResults = [];
                                // Re-enable map interaction
                                if (this.marker) this.marker.setDraggable(true);
                                if (this.map) this.map.setOptions({ draggable: true, clickableIcons: true });

                                // Centre map on current coordinates if already set
                                const currentLat = this.getFieldValue(this.latField);
                                const currentLng = this.getFieldValue(this.lngField);
                                if (currentLat && currentLng) {
                                    this.placeMarkerFromInputs();
                                }
                                this.statusMessage = 'Tap the map to set a location, or drag the marker.';
                            }
                        },

                        // ========================================================
                        //   MAP
                        // ========================================================
                        setupMap() {
                            if (! window.google?.maps) {
                                this.statusMessage = 'Unable to load map.';
                                return;
                            }

                            const container = document.getElementById(this.instanceId + '-map');
                            if (! container) return;

                            this.map = new google.maps.Map(container, {
                                center: { lat: this.centerLat, lng: this.centerLng },
                                zoom: this.zoom,
                                streetViewControl: false,
                                mapTypeControl: false,
                                draggable: this.mode === 'map',
                                clickableIcons: this.mode === 'map',
                            });

                            this.marker = new google.maps.Marker({
                                map: this.map,
                                draggable: true,
                                visible: this.mode === 'map',
                            });

                            // Marker drag in MAP mode
                            this.marker.addListener('dragend', () => {
                                if (this.mode !== 'map') return;
                                const pos = this.marker.getPosition();
                                if (! pos) return;
                                this.reverseGeocode(pos.lat(), pos.lng());
                            });

                            // Map tap in MAP mode
                            this.map.addListener('click', (event) => {
                                if (this.mode !== 'map') return;
                                const lat = event.latLng.lat();
                                const lng = event.latLng.lng();
                                this.placeMarker(lat, lng);
                                this.reverseGeocode(lat, lng);
                            });

                            // Restore marker from existing field values (eg. editing an existing record)
                            const existingLat  = this.getFieldValue(this.latField);
                            const existingLng  = this.getFieldValue(this.lngField);
                            const existingName = this.getFieldValue(this.placeNameField) || '';
                            if (existingLat && existingLng) {
                                this.placeMarker(Number(existingLat), Number(existingLng));
                                this.mapPlaceLabel = existingName || this.mapPlaceLabel;
                            }

                            // Wire up distance/pricing watchers
                            ['pickup_lat','pickup_lng','dropoff_lat','dropoff_lng',
                             'origin_lat','origin_lng','destination_lat','destination_lng'].forEach((name) => {
                                document.querySelectorAll('input').forEach((input) => {
                                    if (input.name === 'data[' + name + ']') {
                                        input.addEventListener('input', () => this.updateDistanceAndPricing());
                                    }
                                });
                            });
                        },

                        placeMarker(lat, lng) {
                            const position = { lat: Number(lat), lng: Number(lng) };
                            if (this.marker) {
                                this.marker.setPosition(position);
                                this.marker.setVisible(true);
                            }
                            if (this.map) {
                                this.map.panTo(position);
                                this.map.setZoom(14);
                            }
                        },

                        placeMarkerFromInputs() {
                            const lat  = this.getFieldValue(this.latField);
                            const lng  = this.getFieldValue(this.lngField);
                            if (lat && lng) this.placeMarker(Number(lat), Number(lng));
                        },

                        reverseGeocode(lat, lng) {
                            const geocoder = new google.maps.Geocoder();
                            geocoder.geocode({ location: { lat, lng } }, (results, status) => {
                                if (status !== 'OK' || !results?.[0]) {
                                    this.statusMessage = 'Tap again or switch to search mode.';
                                    return;
                                }
                                const address = results[0].formatted_address;
                                this.setFieldValue(this.addressField, address);
                                this.setFieldValue(this.placeNameField, results[0].formatted_address || address);
                                this.setFieldValue(this.latField, lat);
                                this.setFieldValue(this.lngField, lng);
                                this.mapPlaceLabel = results[0].formatted_address || address;
                                this.statusMessage = 'Location set.';
                            });
                        },

                        useCurrentLocation() {
                            if (! navigator.geolocation) {
                                this.statusMessage = 'Geolocation unavailable.';
                                return;
                            }
                            this.statusMessage = 'Finding your location…';
                            navigator.geolocation.getCurrentPosition(
                                (position) => {
                                    const lat = position.coords.latitude;
                                    const lng = position.coords.longitude;
                                    this.placeMarker(lat, lng);
                                    this.reverseGeocode(lat, lng);
                                },
                                () => { this.statusMessage = 'Unable to access location.'; },
                                { enableHighAccuracy: true }
                            );
                        },

                        // ========================================================
                        //   SEARCH
                        // ========================================================
                        setupAutocomplete() {
                            const input = document.getElementById(this.instanceId + '-search-input');
                            if (! input || ! window.google?.maps?.places) return;

                            this.autocomplete = new google.maps.places.Autocomplete(input, {
                                types: ['establishment', 'geocode'],
                                componentRestrictions: { country: 'rw' },
                            });
                            this.autocomplete.setFields(['formatted_address', 'name', 'geometry', 'place_id']);

                            this.autocomplete.addListener('place_changed', () => {
                                const place = this.autocomplete.getPlace();
                                if (! place.geometry || ! place.geometry.location) return;

                                const address = place.formatted_address || input.value;
                                const lat = place.geometry.location.lat();
                                const lng = place.geometry.location.lng();
                                const placeName = place.name || address;

                                input.value = address;
                                this.setFieldValue(this.addressField, address);
                                this.setFieldValue(this.placeNameField, placeName);
                                this.setFieldValue(this.latField,  lat);
                                this.setFieldValue(this.lngField,  lng);

                                this.searchOpen  = false;
                                this.searchQuery  = '';
                                this.searchResults = [];
                                this.statusMessage = 'Location set — ' + placeName;
                            });
                        },

                        onSearchInput(query) {
                            this.searchQuery = query;
                            clearTimeout(this.searchDebounce);
                            this.searchDebounce = setTimeout(() => this.performSearch(query), 350);
                        },

                        async performSearch(query) {
                            if (query.length < 2) {
                                this.searchResults = [];
                                return;
                            }
                            try {
                                const response = await fetch(
                                    '/locations/search?q=' + encodeURIComponent(query) + '&country=rw',
                                    { headers: { Accept: 'application/json' } }
                                );
                                const payload = await response.json();
                                this.searchResults = payload.success ? (payload.data || []) : [];
                            } catch (e) {
                                this.searchResults = [];
                            }
                        },

                        async selectPlace(result) {
                            if (! result?.place_id) return;

                            try {
                                const response = await fetch(
                                    '/locations/place-details?place_id=' + encodeURIComponent(result.place_id),
                                    { headers: { Accept: 'application/json' } }
                                );
                                const payload = await response.json();
                                if (! payload.success) return;

                                const data = payload.data;
                                const address   = data.formatted_address  || result.description || '';
                                const placeName = result.main_text         || data.formatted_address || '';
                                const lat       = parseFloat(data.lat) || 0;
                                const lng       = parseFloat(data.lng) || 0;

                                document.getElementById(this.instanceId + '-search-input').value = address;

                                this.setFieldValue(this.addressField,  address);
                                this.setFieldValue(this.placeNameField, placeName);
                                this.setFieldValue(this.latField,  lat);
                                this.setFieldValue(this.lngField,  lng);

                                // Also set the map marker for visual feedback even in search mode
                                if (this.map) this.map.panTo({ lat, lng });
                                if (this.marker) { this.marker.setPosition({ lat, lng }); this.marker.setVisible(false); }

                                this.searchOpen  = false;
                                this.searchQuery  = '';
                                this.searchResults = [];
                                this.statusMessage = 'Location set — ' + placeName;
                            } catch (e) {
                                this.statusMessage = 'Could not load place details.';
                            }
                        },

                        // ========================================================
                        //   SHARED HELPERS
                        // ========================================================
                        getFieldValue(fieldName) {
                            const field = document.querySelector('input[name="data[' + fieldName + ']"]');
                            return field?.value || null;
                        },

                        setFieldValue(fieldName, value) {
                            const field = document.querySelector('input[name="data[' + fieldName + ']"]');
                            if (! field) return;
                            field.value = value;
                            field.dispatchEvent(new Event('input',  { bubbles: true }));
                            field.dispatchEvent(new Event('change', { bubbles: true }));
                        },

                        updateDistanceAndPricing() {
                            const fieldSets = [
                                { pickupLat: 'pickup_lat',  pickupLng: 'pickup_lng',  dropoffLat: 'dropoff_lat',  dropoffLng: 'dropoff_lng' },
                                { pickupLat: 'origin_lat',  pickupLng: 'origin_lng',  dropoffLat: 'destination_lat', dropoffLng: 'destination_lng' },
                            ];
                            let coords = null;
                            for (const set of fieldSets) {
                                const pLat = this.getFieldValue(set.pickupLat);
                                const pLng = this.getFieldValue(set.pickupLng);
                                const dLat = this.getFieldValue(set.dropoffLat);
                                const dLng = this.getFieldValue(set.dropoffLng);
                                if (pLat && pLng && dLat && dLng) {
                                    coords = { pickupLat: Number(pLat), pickupLng: Number(pLng), dropoffLat: Number(dLat), dropoffLng: Number(dLng) };
                                    break;
                                }
                            }
                            if (! coords) return;
                            if (! window.google?.maps?.DistanceMatrixService) return;

                            new google.maps.DistanceMatrixService().getDistanceMatrix({
                                origins:      [new google.maps.LatLng(coords.pickupLat, coords.pickupLng)],
                                destinations: [new google.maps.LatLng(coords.dropoffLat, coords.dropoffLng)],
                                travelMode:   google.maps.TravelMode.DRIVING,
                                unitSystem:   google.maps.UnitSystem.METRIC,
                            }, (response, status) => {
                                if (status !== 'OK' || !response.rows?.[0]?.elements?.[0] || response.rows[0].elements[0].status !== 'OK') return;
                                const distanceKm = (response.rows[0].elements[0].distance.value / 1000).toFixed(2);
                                // emit event so Filament form can pick it up
                                window.dispatchEvent(new CustomEvent('rideconnect-dist-update', {
                                    detail: { distanceKm: Number(distanceKm), coords }
                                }));
                            });
                        },
                    };
                }
                window.locationInput = locationInput;
            })();
        </script>
    </div>
@else
    {{-- Fallback when Google Maps API key is not configured --}}
    <div class="fi-fo-field-wrp space-y-2">
        <label class="text-sm font-medium text-gray-700 block">{{ $label }}</label>
        <div class="flex rounded-md shadow-sm">
            <input type="text"
                  name="data[{{ $addressField }}]"
                  class="flex-1 min-w-0 rounded-l-md border-gray-300 px-3 py-2 text-sm"
                  placeholder="{{ $placeholder }}" />
            <span class="inline-flex items-center rounded-r-md border border-l-0 border-gray-200 bg-gray-50 px-3 text-xs text-gray-400">
                lat / lng not available
            </span>
        </div>
        <p class="text-xs text-red-500">Set LARAMAP_GOOGLE_API_KEY in your environment to enable the map picker.</p>
    </div>
@endif
