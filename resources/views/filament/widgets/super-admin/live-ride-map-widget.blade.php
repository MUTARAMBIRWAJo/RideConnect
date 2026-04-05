<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Real-Time Ride Map</x-slot>
        <x-slot name="description">Live driver locations, active ride pickup points, and drop-off points.</x-slot>

        <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded-md bg-emerald-100 px-2 py-1 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Drivers</span>
                <span class="rounded-md bg-blue-100 px-2 py-1 text-blue-700 dark:bg-blue-950 dark:text-blue-300">Pickups</span>
                <span class="rounded-md bg-rose-100 px-2 py-1 text-rose-700 dark:bg-rose-950 dark:text-rose-300">Drop-offs</span>
                <span id="super-map-meta" class="rounded-md bg-gray-100 px-2 py-1 text-gray-700 dark:bg-gray-800 dark:text-gray-200">Loading map data...</span>
            </div>

            <div id="super-live-map" class="h-[420px] w-full rounded-xl border border-gray-200 dark:border-gray-800" data-endpoint="{{ $endpoint }}" data-lat="{{ $defaultLat }}" data-lng="{{ $defaultLng }}"></div>
        </div>

        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

        <script>
            (function () {
                const mapEl = document.getElementById('super-live-map');
                const metaEl = document.getElementById('super-map-meta');

                if (!mapEl || mapEl.dataset.initialized === '1' || typeof L === 'undefined') {
                    return;
                }

                mapEl.dataset.initialized = '1';

                const center = [Number(mapEl.dataset.lat || '-1.9441'), Number(mapEl.dataset.lng || '30.0619')];
                const endpoint = mapEl.dataset.endpoint;

                const map = L.map(mapEl).setView(center, 12);
                const markersLayer = L.layerGroup().addTo(map);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 19,
                }).addTo(map);

                const icon = (color) => L.divIcon({
                    className: 'custom-dot-icon',
                    html: `<span style="display:inline-block;width:12px;height:12px;border-radius:9999px;background:${color};border:2px solid white;"></span>`,
                    iconSize: [12, 12],
                });

                const render = (payload) => {
                    markersLayer.clearLayers();

                    const drivers = Array.isArray(payload.drivers) ? payload.drivers : [];
                    const rides = Array.isArray(payload.rides) ? payload.rides : [];

                    drivers.forEach((driver) => {
                        const lat = Number(driver.latitude);
                        const lng = Number(driver.longitude);
                        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                            return;
                        }

                        const marker = L.marker([lat, lng], { icon: icon('#16a34a') });
                        marker.bindPopup(`<strong>${driver.name || 'Driver'}</strong><br>Status: ${driver.status || 'unknown'}`);
                        marker.addTo(markersLayer);
                    });

                    rides.forEach((ride) => {
                        const pLat = Number(ride.pickup_lat);
                        const pLng = Number(ride.pickup_lng);
                        const dLat = Number(ride.dropoff_lat);
                        const dLng = Number(ride.dropoff_lng);

                        if (Number.isFinite(pLat) && Number.isFinite(pLng)) {
                            const pickup = L.marker([pLat, pLng], { icon: icon('#2563eb') });
                            pickup.bindPopup(`<strong>Ride #${ride.id}</strong><br>Pickup<br>Status: ${ride.status || 'unknown'}`);
                            pickup.addTo(markersLayer);
                        }

                        if (Number.isFinite(dLat) && Number.isFinite(dLng)) {
                            const dropoff = L.marker([dLat, dLng], { icon: icon('#dc2626') });
                            dropoff.bindPopup(`<strong>Ride #${ride.id}</strong><br>Drop-off`);
                            dropoff.addTo(markersLayer);
                        }
                    });

                    metaEl.textContent = `Drivers: ${drivers.length} | Rides: ${rides.length} | Updated: ${new Date().toLocaleTimeString()}`;
                };

                const refresh = async () => {
                    try {
                        const response = await fetch(endpoint, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}`);
                        }

                        const payload = await response.json();
                        render(payload);
                    } catch (error) {
                        metaEl.textContent = 'Map refresh failed. Retrying...';
                    }
                };

                refresh();
                setInterval(refresh, 30000);
            })();
        </script>
    </x-filament::section>
</x-filament-widgets::widget>
