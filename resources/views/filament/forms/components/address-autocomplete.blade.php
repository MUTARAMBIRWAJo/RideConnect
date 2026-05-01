@php
    $instanceId = 'address-autocomplete-' . md5(($addressField ?? 'pickup_address') . '|' . ($latField ?? 'pickup_lat') . '|' . ($lngField ?? 'pickup_lng'));
    $addressField = $addressField ?? 'pickup_address';
    $latField = $latField ?? 'pickup_lat';
    $lngField = $lngField ?? 'pickup_lng';
    $label = $label ?? 'Pickup Location';
    $placeholder = $placeholder ?? 'Enter address...';
    $apiKey = config('laramaps.api_key');
@endphp

@if($apiKey)
<div class="space-y-3" x-data="addressAutocomplete({
        instanceId: '{{ $instanceId }}',
        addressField: '{{ $addressField }}',
        latField: '{{ $latField }}',
        lngField: '{{ $lngField }}',
        placeholder: '{{ $placeholder }}'
    })" x-init="init()" wire:ignore>
    <div class="flex items-center justify-between gap-3">
        <label class="text-sm font-medium text-gray-700 block">{{ $label }}</label>
        <button
            type="button"
            x-on:click="useCurrentLocation()"
            class="rounded-md border border-gray-300 bg-white px-3 py-1 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50"
        >
            Use current location
        </button>
    </div>

    <input
        type="text"
        id="{{ $instanceId }}-input"
        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
    />

    <div class="text-xs text-gray-500">
        <span x-text="statusMessage"></span>
        <template x-if="distanceText">
            <span class="block mt-1" x-text="distanceText"></span>
        </template>
    </div>

    <div id="{{ $instanceId }}-map" class="h-64 w-full rounded-lg border border-gray-200"></div>

    <script>
        function addressAutocomplete(config) {
            return {
                instanceId: config.instanceId,
                addressField: config.addressField,
                latField: config.latField,
                lngField: config.lngField,
                statusMessage: 'Loading location assistant...',
                distanceText: '',
                map: null,
                marker: null,
                autocomplete: null,
                init() {
                    if (window.google && window.google.maps && window.google.maps.places) {
                        this.setupAutocomplete();
                        this.setupMap();
                        return;
                    }

                    const existingScript = document.querySelector('script[data-rideconnect-maps]');

                    if (existingScript) {
                        existingScript.addEventListener('load', () => {
                            this.setupAutocomplete();
                            this.setupMap();
                        });
                        return;
                    }

                    const script = document.createElement('script');
                    script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent('{{ $apiKey }}') + '&libraries=places';
                    script.async = true;
                    script.defer = true;
                    script.setAttribute('data-rideconnect-maps', 'true');
                    script.onload = () => {
                        this.setupAutocomplete();
                        this.setupMap();
                    };
                    document.head.appendChild(script);
                },
                setupAutocomplete() {
                    const input = document.getElementById(this.instanceId + '-input');
                    if (!input || !window.google?.maps?.places) {
                        this.statusMessage = 'Unable to load Google Places autocomplete.';
                        return;
                    }

                    this.autocomplete = new google.maps.places.Autocomplete(input, {
                        types: ['address'],
                        componentRestrictions: { country: 'rw' }
                    });
                    this.autocomplete.setFields(['formatted_address', 'geometry']);

                    this.autocomplete.addListener('place_changed', () => {
                        const place = this.autocomplete.getPlace();
                        if (!place.geometry || !place.geometry.location) {
                            this.statusMessage = 'Please pick a valid address from the suggestions.';
                            return;
                        }

                        const address = place.formatted_address || input.value;
                        const lat = place.geometry.location.lat();
                        const lng = place.geometry.location.lng();
                        input.value = address;
                        this.updateLocation(address, lat, lng);
                    });
                },
                setupMap() {
                    if (!window.google?.maps) {
                        this.statusMessage = 'Unable to load map.';
                        return;
                    }

                    const container = document.getElementById(this.instanceId + '-map');
                    this.map = new google.maps.Map(container, {
                        center: { lat: -1.9403, lng: 29.8739 },
                        zoom: 11,
                        streetViewControl: false,
                        mapTypeControl: false,
                    });

                    this.marker = new google.maps.Marker({
                        map: this.map,
                        draggable: true,
                    });

                    this.marker.addListener('dragend', () => {
                        const position = this.marker.getPosition();
                        if (!position) {
                            return;
                        }
                        this.reverseGeocode(position.lat(), position.lng());
                    });

                    this.map.addListener('click', (event) => {
                        const lat = event.latLng.lat();
                        const lng = event.latLng.lng();
                        this.placeMarker(lat, lng);
                        this.reverseGeocode(lat, lng);
                    });

                    const initialLat = this.getFieldValue(this.latField);
                    const initialLng = this.getFieldValue(this.lngField);
                    if (initialLat && initialLng) {
                        this.placeMarker(Number(initialLat), Number(initialLng));
                    }

                    const watchFields = [
                        'pickup_lat', 'pickup_lng', 'dropoff_lat', 'dropoff_lng',
                        'origin_lat', 'origin_lng', 'destination_lat', 'destination_lng'
                    ];

                    watchFields.forEach((name) => {
                        const field = document.querySelector(`input[name="data[${name}]"]`);
                        if (field) {
                            field.addEventListener('input', () => this.updateDistanceAndPricing());
                        }
                    });

                    const transportSelect = document.querySelector('select[name="data[transport_type]"]');
                    if (transportSelect) {
                        transportSelect.addEventListener('change', () => this.updateDistanceAndPricing());
                    }

                    this.statusMessage = 'Tap the map, search, or use your current location.';
                },
                getFieldValue(fieldName) {
                    const field = document.querySelector(`input[name="data[${fieldName}]"]`);
                    return field?.value || null;
                },
                setFieldValue(fieldName, value) {
                    const field = document.querySelector(`input[name="data[${fieldName}]"]`);
                    if (!field) {
                        return;
                    }
                    field.value = value;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                },
                updateLocation(address, lat, lng) {
                    this.setFieldValue(this.addressField, address);
                    this.setFieldValue(this.latField, lat);
                    this.setFieldValue(this.lngField, lng);
                    this.placeMarker(lat, lng);
                    this.statusMessage = 'Location set — calculating route details...';
                    this.updateDistanceAndPricing();
                },
                placeMarker(lat, lng) {
                    const position = { lat: Number(lat), lng: Number(lng) };
                    this.marker.setPosition(position);
                    this.map.panTo(position);
                    this.map.setZoom(13);
                },
                reverseGeocode(lat, lng) {
                    const geocoder = new google.maps.Geocoder();
                    geocoder.geocode({ location: { lat, lng } }, (results, status) => {
                        if (status !== 'OK' || !results?.[0]) {
                            this.statusMessage = 'Unable to resolve address from the selected point.';
                            return;
                        }
                        const address = results[0].formatted_address;
                        document.getElementById(this.instanceId + '-input').value = address;
                        this.updateLocation(address, lat, lng);
                    });
                },
                useCurrentLocation() {
                    if (!navigator.geolocation) {
                        this.statusMessage = 'Geolocation is unavailable in your browser.';
                        return;
                    }

                    this.statusMessage = 'Retrieving current location...';
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;
                            this.placeMarker(lat, lng);
                            this.reverseGeocode(lat, lng);
                        },
                        () => {
                            this.statusMessage = 'Unable to access current location. Use the search bar or map instead.';
                        },
                        { enableHighAccuracy: true }
                    );
                },
                updateDistanceAndPricing() {
                    const fieldSets = [
                        {
                            pickupLat: 'pickup_lat',
                            pickupLng: 'pickup_lng',
                            dropoffLat: 'dropoff_lat',
                            dropoffLng: 'dropoff_lng',
                        },
                        {
                            pickupLat: 'origin_lat',
                            pickupLng: 'origin_lng',
                            dropoffLat: 'destination_lat',
                            dropoffLng: 'destination_lng',
                        },
                    ];

                    let coordinates = null;

                    for (const fields of fieldSets) {
                        const pickupLat = this.getFieldValue(fields.pickupLat);
                        const pickupLng = this.getFieldValue(fields.pickupLng);
                        const dropoffLat = this.getFieldValue(fields.dropoffLat);
                        const dropoffLng = this.getFieldValue(fields.dropoffLng);

                        if (pickupLat && pickupLng && dropoffLat && dropoffLng) {
                            coordinates = {
                                pickupLat: Number(pickupLat),
                                pickupLng: Number(pickupLng),
                                dropoffLat: Number(dropoffLat),
                                dropoffLng: Number(dropoffLng),
                            };
                            break;
                        }
                    }

                    if (!coordinates) {
                        return;
                    }

                    if (!window.google?.maps?.DistanceMatrixService) {
                        this.statusMessage = 'Distance matrix service unavailable.';
                        return;
                    }

                    const service = new google.maps.DistanceMatrixService();
                    service.getDistanceMatrix({
                        origins: [new google.maps.LatLng(coordinates.pickupLat, coordinates.pickupLng)],
                        destinations: [new google.maps.LatLng(coordinates.dropoffLat, coordinates.dropoffLng)],
                        travelMode: 'DRIVING',
                        unitSystem: google.maps.UnitSystem.METRIC,
                    }, (response, status) => {
                        if (status !== 'OK' || !response.rows?.[0]?.elements?.[0] || response.rows[0].elements[0].status !== 'OK') {
                            this.statusMessage = 'Unable to calculate route distance.';
                            return;
                        }

                        const element = response.rows[0].elements[0];
                        const distanceKm = (element.distance.value / 1000).toFixed(2);
                        this.distanceText = 'Route distance: ' + distanceKm + ' km';
                        const transportType = document.querySelector('select[name="data[transport_type]"]')?.value || 'CAR';
                        this.calculateSuggestedPrice(Number(distanceKm), transportType);
                    });
                },
                async calculateSuggestedPrice(distanceKm, transportType) {
                    this.statusMessage = 'Calculating suggested fare...';

                    try {
                        const response = await fetch('/api/v1/pricing/calculate', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                distance: distanceKm,
                                transport_type: transportType,
                            }),
                        });

                        if (!response.ok) {
                            throw new Error('Pricing request failed.');
                        }

                        const payload = await response.json();
                        if (!payload.success) {
                            throw new Error(payload.message || 'Could not calculate pricing.');
                        }

                        const priceInput = document.querySelector('input[name="data[price_per_seat]"], input[name="data[fare]"], input[name="data[total_price]"]');
                        if (priceInput) {
                            priceInput.value = payload.price;
                            priceInput.dispatchEvent(new Event('input', { bubbles: true }));
                            priceInput.dispatchEvent(new Event('change', { bubbles: true }));
                        }

                        this.statusMessage = 'Suggested fare updated from route distance.';
                    } catch (error) {
                        console.error(error);
                        this.statusMessage = 'Failed to calculate a suggested fare; enter it manually.';
                    }
                },
            };
        }
    </script>
</div>
@else
<div class="fi-fo-field-wrp">
    <label class="text-sm font-medium text-gray-700 block mb-2">{{ $label }}</label>
    <div class="text-red-600 text-sm mb-2">
        Google Maps API key not configured. Please set LARAMAP_GOOGLE_API_KEY or GOOGLE_MAPS_API_KEY in your environment.
    </div>
    <input
        type="text"
        name="data[{{ $addressField }}]"
        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        placeholder="{{ $placeholder }}"
    />
</div>
@endif