@php($mapId = 'ride-map-' . uniqid())
@php($toggleMarkersId = 'toggle-markers-' . uniqid())
@php($toggleHeatmapId = 'toggle-heatmap-' . uniqid())
@php($toggleTrafficId = 'toggle-traffic-' . uniqid())
@php($driversCountId = 'drivers-count-' . uniqid())
@php($ridesCountId = 'rides-count-' . uniqid())
@php($mapStatusId = 'map-status-' . uniqid())

<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">RideConnect Smart Mobility Map</h3>
                <p class="text-xs text-gray-500 dark:text-gray-300">Live drivers, pickups, destinations, demand heatmap, and traffic in Kigali.</p>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <span id="{{ $driversCountId }}" class="px-2 py-1 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">Drivers: 0</span>
                <span id="{{ $ridesCountId }}" class="px-2 py-1 rounded bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200">Rides: 0</span>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-3">
            <button id="{{ $toggleMarkersId }}" type="button" class="px-3 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200">
                Hide Markers
            </button>
            <button id="{{ $toggleHeatmapId }}" type="button" class="px-3 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200">
                Show Heatmap
            </button>
            <button id="{{ $toggleTrafficId }}" type="button" class="px-3 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200">
                Show Traffic
            </button>
        </div>

        <div class="mb-3 text-xs text-gray-600 dark:text-gray-300">
            <span class="inline-flex items-center mr-3"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 mr-1.5"></span>Driver Online</span>
            <span class="inline-flex items-center mr-3"><span class="w-2.5 h-2.5 rounded-full bg-gray-500 mr-1.5"></span>Driver Offline</span>
            <span class="inline-flex items-center mr-3"><span class="w-2.5 h-2.5 rounded-full bg-blue-500 mr-1.5"></span>Pickup</span>
            <span class="inline-flex items-center"><span class="w-2.5 h-2.5 rounded-full bg-red-500 mr-1.5"></span>Destination</span>
        </div>

        <div
            id="{{ $mapId }}"
            class="w-full h-[420px] rounded-xl border border-gray-200 dark:border-gray-700"
            data-api-key="{{ config('laramaps.api_key', config('services.google_maps.key')) }}"
            data-endpoint="{{ route('api.map.live-data') }}"
            data-default-lat="-1.9441"
            data-default-lng="30.0619"
            data-default-zoom="12"
        ></div>

        <div id="{{ $mapStatusId }}" class="mt-2 hidden rounded-md border px-3 py-2 text-xs"></div>

        @once
            <script>
                window.initMap = window.initMap || function () {
                    console.log('Google Maps Loaded:', typeof google !== 'undefined');
                };
            </script>
            <script async defer
                src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=visualization,places&v=weekly&callback=initMap">
            </script>
        @endonce

        <script src="https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js"></script>
        <script>
            (function () {
                const mapElement = document.getElementById('{{ $mapId }}');
                if (!mapElement || mapElement.dataset.initialized === '1') {
                    return;
                }

                mapElement.dataset.initialized = '1';

                const state = {
                    map: null,
                    infoWindow: null,
                    heatmap: null,
                    trafficLayer: null,
                    markerCluster: null,
                    markersVisible: true,
                    heatmapVisible: false,
                    trafficVisible: false,
                    isRefreshing: false,
                    driverMarkers: new Map(),
                    pickupMarkers: new Map(),
                    dropoffMarkers: new Map(),
                    refreshTimer: null,
                };

                const counts = {
                    drivers: document.getElementById('{{ $driversCountId }}'),
                    rides: document.getElementById('{{ $ridesCountId }}'),
                };

                const mapStatus = document.getElementById('{{ $mapStatusId }}');

                const controls = {
                    markers: document.getElementById('{{ $toggleMarkersId }}'),
                    heatmap: document.getElementById('{{ $toggleHeatmapId }}'),
                    traffic: document.getElementById('{{ $toggleTrafficId }}'),
                };

                const apiKey = mapElement.dataset.apiKey || '';
                const endpoint = mapElement.dataset.endpoint;
                const defaultCenter = {
                    lat: Number(mapElement.dataset.defaultLat || '-1.9441'),
                    lng: Number(mapElement.dataset.defaultLng || '30.0619'),
                };
                const defaultZoom = Number(mapElement.dataset.defaultZoom || '12');

                const loadGoogleMaps = function () {
                    if (window.google && window.google.maps && window.google.maps.visualization) {
                        console.log('Google Maps Loaded:', true);
                        return Promise.resolve();
                    }

                    if (window.loadGoogleMapsScript) {
                        return window.loadGoogleMapsScript(apiKey, 'initMap').then(function () {
                            if (!window.google?.maps?.visualization) {
                                throw new Error('Google Maps loaded without visualization library.');
                            }
                        });
                    }

                    if (window.__rideConnectGoogleMapsPromise) {
                        return window.__rideConnectGoogleMapsPromise;
                    }

                    window.__rideConnectGoogleMapsPromise = new Promise(function (resolve, reject) {
                        // Google invokes this callback when API key auth fails.
                        window.gm_authFailure = function () {
                            reject(new Error('Google Maps authentication failed'));
                        };

                        const script = document.createElement('script');
                        script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(apiKey) + '&libraries=visualization';
                        script.async = true;
                        script.defer = true;
                        script.onload = function () {
                            console.log('Google Maps Loaded:', typeof google !== 'undefined');
                            resolve();
                        };
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });

                    return window.__rideConnectGoogleMapsPromise;
                };

                const showStatus = function (message, isError) {
                    if (!mapStatus) {
                        return;
                    }

                    mapStatus.textContent = message;
                    mapStatus.classList.remove('hidden', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-800', 'border-red-200', 'bg-red-50', 'text-red-700');

                    if (isError) {
                        mapStatus.classList.add('border-red-200', 'bg-red-50', 'text-red-700');
                    } else {
                        mapStatus.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-800');
                    }
                };

                const hideStatus = function () {
                    if (!mapStatus) {
                        return;
                    }

                    mapStatus.classList.add('hidden');
                    mapStatus.textContent = '';
                };

                const debounce = function (fn, waitMs) {
                    let timeoutId = null;
                    return function () {
                        clearTimeout(timeoutId);
                        timeoutId = setTimeout(fn, waitMs);
                    };
                };

                const markerIcon = function (color) {
                    return {
                        path: google.maps.SymbolPath.CIRCLE,
                        fillColor: color,
                        fillOpacity: 1,
                        strokeColor: '#ffffff',
                        strokeWeight: 1,
                        scale: 7,
                    };
                };

                const setMarker = function (store, id, markerData) {
                    const existingMarker = store.get(id);
                    if (existingMarker) {
                        existingMarker.setPosition(markerData.position);
                        existingMarker.setIcon(markerData.icon);
                        existingMarker.__infoContent = markerData.info;
                        return existingMarker;
                    }

                    const marker = new google.maps.Marker({
                        position: markerData.position,
                        map: state.markersVisible ? state.map : null,
                        title: markerData.title,
                        icon: markerData.icon,
                    });

                    marker.__infoContent = markerData.info;
                    marker.addListener('click', function () {
                        state.infoWindow.setContent(marker.__infoContent);
                        state.infoWindow.open({ map: state.map, anchor: marker });
                    });

                    store.set(id, marker);
                    return marker;
                };

                const removeMissingMarkers = function (store, activeIds) {
                    Array.from(store.entries()).forEach(function ([id, marker]) {
                        if (activeIds.has(id)) {
                            return;
                        }
                        marker.setMap(null);
                        store.delete(id);
                    });
                };

                const allMarkers = function () {
                    return [
                        ...state.driverMarkers.values(),
                        ...state.pickupMarkers.values(),
                        ...state.dropoffMarkers.values(),
                    ];
                };

                const rebuildCluster = function () {
                    if (state.markerCluster && typeof state.markerCluster.clearMarkers === 'function') {
                        state.markerCluster.clearMarkers();
                    }

                    if (!state.markersVisible) {
                        state.markerCluster = null;
                        return;
                    }

                    const markers = allMarkers();
                    if (!markers.length || !window.markerClusterer || !window.markerClusterer.MarkerClusterer) {
                        return;
                    }

                    state.markerCluster = new window.markerClusterer.MarkerClusterer({
                        map: state.map,
                        markers: markers,
                    });
                };

                const applyVisibility = function () {
                    allMarkers().forEach(function (marker) {
                        marker.setMap(state.markersVisible ? state.map : null);
                    });

                    if (state.heatmap) {
                        state.heatmap.setMap(state.heatmapVisible ? state.map : null);
                    }

                    if (state.trafficLayer) {
                        state.trafficLayer.setMap(state.trafficVisible ? state.map : null);
                    }

                    rebuildCluster();

                    controls.markers.textContent = state.markersVisible ? 'Hide Markers' : 'Show Markers';
                    controls.heatmap.textContent = state.heatmapVisible ? 'Hide Heatmap' : 'Show Heatmap';
                    controls.traffic.textContent = state.trafficVisible ? 'Hide Traffic' : 'Show Traffic';
                };

                const renderData = function (payload) {
                    const drivers = Array.isArray(payload.drivers) ? payload.drivers : [];
                    const rides = Array.isArray(payload.rides) ? payload.rides : [];

                    const driverIds = new Set();
                    drivers.forEach(function (driver) {
                        const lat = Number(driver.latitude);
                        const lng = Number(driver.longitude);
                        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                            return;
                        }

                        const id = String(driver.id);
                        driverIds.add(id);

                        const status = String(driver.status || 'offline').toLowerCase();
                        const statusColor = status === 'online' ? '#16a34a' : '#6b7280';

                        setMarker(state.driverMarkers, id, {
                            position: { lat: lat, lng: lng },
                            title: driver.name || ('Driver #' + id),
                            icon: markerIcon(statusColor),
                            info: [
                                '<div style="font-size:12px;line-height:1.4">',
                                '<strong>Driver:</strong> ' + (driver.name || ('Driver #' + id)) + '<br>',
                                '<strong>Status:</strong> ' + status,
                                '</div>'
                            ].join(''),
                        });
                    });

                    const pickupIds = new Set();
                    const dropoffIds = new Set();
                    const heatPoints = [];

                    rides.forEach(function (ride) {
                        const rideId = String(ride.id);
                        const pickupLat = Number(ride.pickup_lat);
                        const pickupLng = Number(ride.pickup_lng);
                        const dropoffLat = Number(ride.dropoff_lat);
                        const dropoffLng = Number(ride.dropoff_lng);

                        const canPickup = Number.isFinite(pickupLat) && Number.isFinite(pickupLng);
                        const canDropoff = Number.isFinite(dropoffLat) && Number.isFinite(dropoffLng);

                        if (canPickup) {
                            const id = 'pickup-' + rideId;
                            pickupIds.add(id);
                            setMarker(state.pickupMarkers, id, {
                                position: { lat: pickupLat, lng: pickupLng },
                                title: 'Ride #' + rideId + ' Pickup',
                                icon: markerIcon('#2563eb'),
                                info: [
                                    '<div style="font-size:12px;line-height:1.4">',
                                    '<strong>Ride ID:</strong> ' + rideId + '<br>',
                                    '<strong>Pickup -> Dropoff:</strong><br>',
                                    pickupLat.toFixed(5) + ', ' + pickupLng.toFixed(5) + ' -> ' +
                                    (canDropoff ? dropoffLat.toFixed(5) + ', ' + dropoffLng.toFixed(5) : 'N/A') + '<br>',
                                    '<strong>Status:</strong> ' + (ride.status || 'unknown'),
                                    '</div>'
                                ].join(''),
                            });

                            heatPoints.push(new google.maps.LatLng(pickupLat, pickupLng));
                        }

                        if (canDropoff) {
                            const id = 'dropoff-' + rideId;
                            dropoffIds.add(id);
                            setMarker(state.dropoffMarkers, id, {
                                position: { lat: dropoffLat, lng: dropoffLng },
                                title: 'Ride #' + rideId + ' Destination',
                                icon: markerIcon('#dc2626'),
                                info: [
                                    '<div style="font-size:12px;line-height:1.4">',
                                    '<strong>Ride ID:</strong> ' + rideId + '<br>',
                                    '<strong>Pickup -> Dropoff:</strong><br>',
                                    (canPickup ? pickupLat.toFixed(5) + ', ' + pickupLng.toFixed(5) : 'N/A') + ' -> ' +
                                    dropoffLat.toFixed(5) + ', ' + dropoffLng.toFixed(5) + '<br>',
                                    '<strong>Status:</strong> ' + (ride.status || 'unknown'),
                                    '</div>'
                                ].join(''),
                            });
                        }
                    });

                    removeMissingMarkers(state.driverMarkers, driverIds);
                    removeMissingMarkers(state.pickupMarkers, pickupIds);
                    removeMissingMarkers(state.dropoffMarkers, dropoffIds);

                    state.heatmap.setData(heatPoints);
                    applyVisibility();

                    if (counts.drivers) {
                        counts.drivers.textContent = 'Drivers: ' + drivers.length;
                    }
                    if (counts.rides) {
                        counts.rides.textContent = 'Rides: ' + rides.length;
                    }
                };

                const refreshData = function () {
                    if (state.isRefreshing) {
                        return;
                    }

                    state.isRefreshing = true;

                    fetch(endpoint, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('Live map API returned status ' + response.status);
                            }

                            return response.json();
                        })
                        .then(function (payload) {
                            hideStatus();
                            renderData(payload);
                        })
                        .catch(function (error) {
                            showStatus('Map data refresh failed: ' + (error?.message || 'unknown error'), true);
                        })
                        .finally(function () {
                            state.isRefreshing = false;
                        });
                };

                const debouncedRefresh = debounce(refreshData, 300);

                const startRefreshLoop = function () {
                    if (state.refreshTimer) {
                        clearInterval(state.refreshTimer);
                    }

                    state.refreshTimer = setInterval(function () {
                        debouncedRefresh();
                    }, 10000);
                };

                const initMap = function () {
                    state.map = new google.maps.Map(mapElement, {
                        center: defaultCenter,
                        zoom: defaultZoom,
                        mapTypeControl: false,
                        streetViewControl: false,
                        fullscreenControl: true,
                    });

                    state.infoWindow = new google.maps.InfoWindow();
                    state.heatmap = new google.maps.visualization.HeatmapLayer({
                        data: [],
                        radius: 28,
                        opacity: 0.7,
                    });
                    state.trafficLayer = new google.maps.TrafficLayer();

                    controls.markers.addEventListener('click', function () {
                        state.markersVisible = !state.markersVisible;
                        applyVisibility();
                    });

                    controls.heatmap.addEventListener('click', function () {
                        state.heatmapVisible = !state.heatmapVisible;
                        applyVisibility();
                    });

                    controls.traffic.addEventListener('click', function () {
                        state.trafficVisible = !state.trafficVisible;
                        applyVisibility();
                    });

                    document.addEventListener('visibilitychange', function () {
                        if (!document.hidden) {
                            debouncedRefresh();
                        }
                    });

                    refreshData();
                    startRefreshLoop();
                };

                const bootstrap = function () {
                    if (!apiKey) {
                        mapElement.innerHTML = '<div class="h-full flex items-center justify-center text-sm text-gray-500">Missing GOOGLE_MAPS_API_KEY</div>';
                        return;
                    }

                    loadGoogleMaps()
                        .then(initMap)
                        .catch(function (error) {
                            const message = (error && error.message === 'Google Maps authentication failed')
                                ? 'Google Maps key rejected (check API restrictions/billing).'
                                : 'Unable to load Google Maps';

                            mapElement.innerHTML = '<div class="h-full flex items-center justify-center text-sm text-red-600">' + message + '</div>';
                            showStatus(message, true);
                        });
                };

                const observer = new IntersectionObserver(function (entries) {
                    const isVisible = entries.some(function (entry) {
                        return entry.isIntersecting;
                    });

                    if (!isVisible) {
                        return;
                    }

                    observer.disconnect();
                    bootstrap();
                }, {
                    root: null,
                    threshold: 0.15,
                });

                observer.observe(mapElement);
            })();
        </script>
    </x-filament::section>
</x-filament-widgets::widget>
