@php
    $instanceId = 'map-picker-' . md5(($latField ?? 'lat') . '|' . ($lngField ?? 'lng') . '|' . ($addressField ?? 'address'));
    $label = $label ?? 'Pick location on map';
    $height = $height ?? 280;
    $centerLat = $centerLat ?? -1.9441;
    $centerLng = $centerLng ?? 30.0619;
    $zoom = $zoom ?? 12;
@endphp

<div class="fi-fo-field-wrp" x-data="{}" wire:ignore>
    <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-medium text-gray-700">{{ $label }}</span>
        <span class="text-xs text-gray-500">Click map to set coordinates</span>
    </div>

    <div id="{{ $instanceId }}" style="height: {{ (int) $height }}px; border-radius: 10px; border: 1px solid #e5e7eb;"></div>

    <script>
        (function initMapPicker() {
            const rootId = @json($instanceId);
            const latField = @json($latField ?? 'pickup_lat');
            const lngField = @json($lngField ?? 'pickup_lng');
            const addressField = @json($addressField ?? 'pickup_address');
            const centerLat = Number(@json($centerLat));
            const centerLng = Number(@json($centerLng));
            const zoom = Number(@json($zoom));

            const inputSelector = (field) => `input[name="data[${field}]"]`;
            const setInputValue = (field, value) => {
                const input = document.querySelector(inputSelector(field));
                if (!input) return;
                input.value = value;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            };

            const setupMap = () => {
                const container = document.getElementById(rootId);
                if (!container || container.dataset.initialized === '1') return;
                container.dataset.initialized = '1';

                const map = L.map(container).setView([centerLat, centerLng], zoom);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);

                let marker = null;
                const latInput = document.querySelector(inputSelector(latField));
                const lngInput = document.querySelector(inputSelector(lngField));
                if (latInput && lngInput && latInput.value && lngInput.value) {
                    const lat = Number(latInput.value);
                    const lng = Number(lngInput.value);
                    if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
                        marker = L.marker([lat, lng]).addTo(map);
                        map.setView([lat, lng], Math.max(zoom, 14));
                    }
                }

                map.on('click', (event) => {
                    const lat = Number(event.latlng.lat).toFixed(6);
                    const lng = Number(event.latlng.lng).toFixed(6);

                    if (!marker) {
                        marker = L.marker([lat, lng]).addTo(map);
                    } else {
                        marker.setLatLng([lat, lng]);
                    }

                    setInputValue(latField, lat);
                    setInputValue(lngField, lng);
                    setInputValue(addressField, `Pinned on map (${lat}, ${lng})`);
                });

                setTimeout(() => map.invalidateSize(), 250);
            };

            const loadLeaflet = () => {
                if (window.L) {
                    setupMap();
                    return;
                }

                if (!document.querySelector('link[data-leaflet="1"]')) {
                    const css = document.createElement('link');
                    css.rel = 'stylesheet';
                    css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                    css.setAttribute('data-leaflet', '1');
                    document.head.appendChild(css);
                }

                if (!document.querySelector('script[data-leaflet="1"]')) {
                    const script = document.createElement('script');
                    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                    script.async = true;
                    script.defer = true;
                    script.setAttribute('data-leaflet', '1');
                    script.onload = setupMap;
                    document.head.appendChild(script);
                } else {
                    const wait = setInterval(() => {
                        if (window.L) {
                            clearInterval(wait);
                            setupMap();
                        }
                    }, 100);
                }
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', loadLeaflet, { once: true });
            } else {
                loadLeaflet();
            }
        })();
    </script>
</div>
