<div class="fi-section rounded-2xl p-4 sm:p-6">
  <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
    <div>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">AI Demand Heatmap — Kigali</h3>
      <div class="text-sm text-gray-500 dark:text-gray-300">Predicted demand in next 30 minutes</div>
    </div>
    @php($activeDriversCounterId = 'active-drivers-counter-' . uniqid())
    <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
      <span id="{{ $activeDriversCounterId }}" class="px-2 py-1 rounded-full bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200 font-semibold">Active Drivers: 0</span>
      <span class="w-3 h-3 rounded-full bg-blue-100 inline-block"></span> Low
      <span class="w-3 h-3 rounded-full bg-amber-100 inline-block ml-3"></span> Medium
      <span class="w-3 h-3 rounded-full bg-red-100 inline-block ml-3"></span> High
    </div>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
    <div class="bg-gradient-to-br from-gray-50 to-white dark:from-gray-800 dark:to-gray-800 rounded-lg overflow-hidden">
      @php($mapId = 'rideconnect-live-map-' . uniqid())
      @php($toggleDriversId = 'toggle-drivers-' . uniqid())
      @php($togglePassengersId = 'toggle-passengers-' . uniqid())
      @php($toggleHeatmapId = 'toggle-heatmap-' . uniqid())
      @php($toggleRidesId = 'toggle-rides-' . uniqid())
      @php($replayRideId = 'replay-ride-' . uniqid())
      @php($replayPlayId = 'replay-play-' . uniqid())
      @php($replayPauseId = 'replay-pause-' . uniqid())
      @php($replaySpeedId = 'replay-speed-' . uniqid())
      @php($popupId = 'map-data-popup-' . uniqid())
      @php($popupTitleId = 'map-data-popup-title-' . uniqid())
      @php($popupBodyId = 'map-data-popup-body-' . uniqid())
      @php($popupCloseId = 'map-data-popup-close-' . uniqid())

      <div
        id="{{ $mapId }}"
        class="h-[300px] sm:h-[360px] lg:h-[420px] w-full"
        style="min-height: 320px;"
        data-google-maps-key="{{ config('services.google_maps.key') }}"
        data-supabase-url="{{ config('supabase.url') }}"
        data-supabase-key="{{ config('supabase.key') }}"
        data-map-endpoint="{{ url('/api/admin/map-data') }}"
        data-demand-endpoint="{{ url('/api/admin/demand-heatmap') }}"
        data-live-requests-endpoint="{{ url('/api/admin/live-requests') }}"
        data-route-history-template="{{ url('/api/admin/rides/__RIDE__/route-history') }}"
        data-refresh-ms="15000"
        data-kigali-lat="-1.9441"
        data-kigali-lng="30.0619"
      ></div>

      <div id="{{ $mapId }}-status" class="hidden px-3 py-2 text-xs text-amber-700 bg-amber-50 border-t border-amber-200"></div>

      <div class="grid grid-cols-1 gap-2 border-t border-gray-200 p-3 sm:grid-cols-2 lg:grid-cols-3 dark:border-gray-700">
        <button id="{{ $toggleDriversId }}" type="button" class="w-full cursor-pointer px-3 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200">Toggle Drivers</button>
        <button id="{{ $togglePassengersId }}" type="button" class="w-full cursor-pointer px-3 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200">Toggle Passengers</button>
        <button id="{{ $toggleHeatmapId }}" type="button" class="w-full cursor-pointer px-3 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200">Show Demand Heatmap</button>
        <button id="{{ $toggleRidesId }}" type="button" class="w-full cursor-pointer px-3 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200">Show Active Rides</button>
        <button id="{{ $replayRideId }}" type="button" class="w-full cursor-pointer px-3 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200">Replay Ride</button>
        <button id="{{ $replayPlayId }}" type="button" class="w-full cursor-pointer px-3 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200">Play</button>
        <button id="{{ $replayPauseId }}" type="button" class="w-full cursor-pointer px-3 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200">Pause</button>
        <select id="{{ $replaySpeedId }}" class="w-full px-2 py-1.5 text-xs rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-200">
          <option value="1">1x</option>
          <option value="2">2x</option>
          <option value="4">4x</option>
        </select>
      </div>

      @php($mapMetricsId = 'map-metrics-' . uniqid())
      <div id="{{ $mapMetricsId }}" class="px-3 py-2 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-300 flex flex-wrap gap-3">
        <span data-metric="drivers">Drivers: 0</span>
        <span data-metric="passengers">Passengers: 0</span>
        <span data-metric="rides">Active Rides: 0</span>
      </div>

      <div id="{{ $popupId }}" class="hidden px-3 py-2 border-t border-blue-200 bg-blue-50 text-xs text-blue-900">
        <div class="flex items-center justify-between gap-2">
          <strong id="{{ $popupTitleId }}">Data Summary</strong>
          <button id="{{ $popupCloseId }}" type="button" class="cursor-pointer px-2 py-0.5 rounded border border-blue-300 text-blue-700">Close</button>
        </div>
        <div id="{{ $popupBodyId }}" class="mt-1 leading-5"></div>
      </div>
    </div>

    <div class="space-y-4">
      <div class="fi-section rounded-lg p-4">
        <h4 class="text-sm font-medium text-gray-900 dark:text-white">Driver Availability</h4>
        @php($smallDonutId = 'driverAvailabilityDonutSmall-' . uniqid())
        <div class="mt-3 h-[220px] w-full max-w-[280px]">
          <canvas id="{{ $smallDonutId }}" class="h-full w-full"></canvas>
        </div>
      </div>

      <div class="fi-section p-4 rounded-lg">
        <h4 class="text-sm font-medium text-gray-900 dark:text-white">Legend</h4>
        <div class="mt-2 text-sm text-gray-500 dark:text-gray-300">Low → High demand gradient (blue → amber → red). Heat intensity driven by AI predictions.</div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
  <script src="https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js"></script>
  <script>
    (function(){
      const chartId = '{{ $smallDonutId }}';
      const ctx = document.getElementById(chartId);
      if(!ctx) return;
      try{
        window.__rideConnectCharts = window.__rideConnectCharts || {};
        if (window.__rideConnectCharts[chartId]) {
          window.__rideConnectCharts[chartId].destroy();
        }

        window.__rideConnectCharts[chartId] = new Chart(ctx.getContext('2d'), {
          type: 'doughnut',
          data: { labels:['Available','Busy','Offline'], datasets:[{ data:[{{ $available ?? 0 }}, {{ $busy ?? 0 }}, {{ $offline ?? 0 }}], backgroundColor:['var(--color-success)','var(--color-primary)','var(--color-muted)'] }]},
          options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom'}} }
        });
      }catch(e){}
    })();

    (function () {
      const mapRoot = document.getElementById('{{ $mapId }}');
      if (!mapRoot) return;

      const apiKey = mapRoot.dataset.googleMapsKey;
      const supabaseUrl = mapRoot.dataset.supabaseUrl;
      const supabaseKey = mapRoot.dataset.supabaseKey;
      const endpoint = mapRoot.dataset.mapEndpoint;
      const demandEndpoint = mapRoot.dataset.demandEndpoint;
      const liveRequestsEndpoint = mapRoot.dataset.liveRequestsEndpoint;
      const routeHistoryTemplate = mapRoot.dataset.routeHistoryTemplate;
      const refreshMs = Number(mapRoot.dataset.refreshMs || '5000');
      const kigaliCenter = {
        lat: Number(mapRoot.dataset.kigaliLat || '-1.9441'),
        lng: Number(mapRoot.dataset.kigaliLng || '30.0619'),
      };

      const toggleDriversBtn = document.getElementById('{{ $toggleDriversId }}');
      const togglePassengersBtn = document.getElementById('{{ $togglePassengersId }}');
      const toggleHeatmapBtn = document.getElementById('{{ $toggleHeatmapId }}');
      const toggleRidesBtn = document.getElementById('{{ $toggleRidesId }}');
      const replayRideBtn = document.getElementById('{{ $replayRideId }}');
      const replayPlayBtn = document.getElementById('{{ $replayPlayId }}');
      const replayPauseBtn = document.getElementById('{{ $replayPauseId }}');
      const replaySpeedSelect = document.getElementById('{{ $replaySpeedId }}');
      const activeDriversCounter = document.getElementById('{{ $activeDriversCounterId }}');
      const statusBanner = document.getElementById('{{ $mapId }}-status');
      const mapMetrics = document.getElementById('{{ $mapMetricsId }}');
      const popupPanel = document.getElementById('{{ $popupId }}');
      const popupTitle = document.getElementById('{{ $popupTitleId }}');
      const popupBody = document.getElementById('{{ $popupBodyId }}');
      const popupCloseBtn = document.getElementById('{{ $popupCloseId }}');

      if (!apiKey) {
        mapRoot.innerHTML = '<div class="h-full w-full flex items-center justify-center text-sm text-gray-500">Google Maps API key is missing</div>';
        return;
      }

      const loadGoogleMapsScript = function (key) {
        if (window.google && window.google.maps) {
          return Promise.resolve();
        }

        if (window.__rideConnectGoogleMapsPromise) {
          return window.__rideConnectGoogleMapsPromise;
        }

        window.__rideConnectGoogleMapsPromise = new Promise(function (resolve, reject) {
          // Google invokes this global hook when the key is invalid, blocked by referrer,
          // or billing/API activation is misconfigured.
          window.gm_authFailure = function () {
            reject(new Error('Google Maps authentication failed'));
          };

          const script = document.createElement('script');
          script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) + '&libraries=visualization';
          script.async = true;
          script.defer = true;
          script.onload = resolve;
          script.onerror = reject;
          document.head.appendChild(script);
        });

        return window.__rideConnectGoogleMapsPromise;
      };

      window.driverMarkers = window.driverMarkers || {};
      window.passengerMarkers = window.passengerMarkers || {};
      window.ridePolylines = window.ridePolylines || {};

      const driverMarkers = window.driverMarkers;
      const passengerMarkers = window.passengerMarkers;
      const ridePolylines = window.ridePolylines;

      const state = {
        map: null,
        directionsService: null,
        clusterer: null,
        heatmapLayer: null,
        requestMarkers: {},
        requestWindows: {},
        activeRequestIds: new Set(),
        visibility: {
          drivers: true,
          passengers: true,
          heatmap: false,
          rides: true,
        },
        replay: {
          marker: null,
          polyline: null,
          points: [],
          index: 0,
          timer: null,
          speed: 1,
        },
        supabase: null,
        realtimeChannels: [],
        realtimeSyncTimer: null,
        popupTimer: null,
        latestSnapshot: {
          drivers: [],
          passengers: [],
          rides: [],
          liveRequests: [],
          activeDriverCount: 0,
          activeRideCount: 0,
          heatmapPoints: 0,
        },
        refreshTimer: null,
        requestsTimer: null,
      };

      const showDataPopup = function (title, lines) {
        if (!popupPanel || !popupTitle || !popupBody) {
          return;
        }

        popupTitle.textContent = title;
        popupBody.innerHTML = lines.join('<br>');
        popupPanel.classList.remove('hidden');

        if (state.popupTimer) {
          clearTimeout(state.popupTimer);
        }

        state.popupTimer = setTimeout(function () {
          popupPanel.classList.add('hidden');
        }, 6000);
      };

      const showStatus = function (message) {
        if (!statusBanner) {
          return;
        }

        statusBanner.textContent = message;
        statusBanner.classList.remove('hidden');
      };

      const hideStatus = function () {
        if (!statusBanner) {
          return;
        }

        statusBanner.textContent = '';
        statusBanner.classList.add('hidden');
      };

      const fetchJson = function (url) {
        return fetch(url, { cache: 'no-store', credentials: 'same-origin' })
          .then(function (response) {
            const contentType = response.headers.get('content-type') || '';
            if (!response.ok || !contentType.includes('application/json')) {
              throw new Error('Unexpected API response for ' + url + ' (status ' + response.status + ')');
            }

            return response.json();
          });
      };

      const scheduleRealtimeSync = function () {
        if (state.realtimeSyncTimer) {
          clearTimeout(state.realtimeSyncTimer);
        }

        state.realtimeSyncTimer = setTimeout(function () {
          fetchMapData();
          updateLiveRequests();

          if (state.visibility.heatmap) {
            fetchDemandHeatmap();
          }
        }, 250);
      };

      const connectSupabaseRealtime = function () {
        if (!supabaseUrl || !supabaseKey || !window.supabase || typeof window.supabase.createClient !== 'function') {
          return;
        }

        state.supabase = window.supabase.createClient(supabaseUrl, supabaseKey, {
          auth: {
            persistSession: false,
            autoRefreshToken: false,
          },
        });

        const handleRealtimeChange = function () {
          scheduleRealtimeSync();
        };

        const driverChannel = state.supabase
          .channel('rideconnect-driver-locations')
          .on('postgres_changes', {
            event: '*',
            schema: 'public',
            table: 'driver_locations',
          }, handleRealtimeChange)
          .subscribe();

        const tripsChannel = state.supabase
          .channel('rideconnect-trips')
          .on('postgres_changes', {
            event: '*',
            schema: 'public',
            table: 'trips',
          }, handleRealtimeChange)
          .subscribe();

        state.realtimeChannels = [driverChannel, tripsChannel];
      };

      const getClustererClass = function () {
        if (window.markerClusterer && window.markerClusterer.MarkerClusterer) {
          return window.markerClusterer.MarkerClusterer;
        }

        return window.MarkerClusterer;
      };

      const updateDriverCluster = function () {
        if (!state.map) {
          return;
        }

        if (state.clusterer && typeof state.clusterer.clearMarkers === 'function') {
          state.clusterer.clearMarkers();
        }

        const ClustererClass = getClustererClass();
        if (!ClustererClass || !state.visibility.drivers) {
          return;
        }

        const markers = Object.values(driverMarkers);
        state.clusterer = new ClustererClass({ map: state.map, markers: markers });
      };

      const animateMarkerTo = function (marker, target, durationMs) {
        const start = marker.getPosition();
        if (!start) {
          marker.setPosition(target);
          return;
        }

        if (marker.__animationFrame) {
          cancelAnimationFrame(marker.__animationFrame);
        }

        const startLat = start.lat();
        const startLng = start.lng();
        const deltaLat = target.lat - startLat;
        const deltaLng = target.lng - startLng;
        const startedAt = performance.now();

        const tick = function (now) {
          const elapsed = now - startedAt;
          const t = Math.min(1, elapsed / durationMs);
          const ease = t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;

          marker.setPosition({
            lat: startLat + (deltaLat * ease),
            lng: startLng + (deltaLng * ease),
          });

          if (t < 1) {
            marker.__animationFrame = requestAnimationFrame(tick);
          }
        };

        marker.__animationFrame = requestAnimationFrame(tick);
      };

      const removeMarkersNotInSet = function (container, keepIds) {
        Object.keys(container).forEach(function (id) {
          if (!keepIds.has(String(id))) {
            container[id].setMap(null);
            delete container[id];
          }
        });
      };

      const updateDrivers = function (drivers, carIcon) {
        const keepIds = new Set();

        drivers.forEach(function (driver) {
          const id = String(driver.id);
          keepIds.add(id);

          const target = { lat: Number(driver.lat), lng: Number(driver.lng) };
          if (!driverMarkers[id]) {
            driverMarkers[id] = new google.maps.Marker({
              position: target,
              map: state.visibility.drivers ? state.map : null,
              icon: carIcon,
              title: 'Driver #' + driver.id,
            });
          } else {
            animateMarkerTo(driverMarkers[id], target, 1500);
            driverMarkers[id].setTitle('Driver #' + driver.id);
            if (state.visibility.drivers && !driverMarkers[id].getMap()) {
              driverMarkers[id].setMap(state.map);
            }
            if (!state.visibility.drivers) {
              driverMarkers[id].setMap(null);
            }
          }
        });

        removeMarkersNotInSet(driverMarkers, keepIds);
        updateDriverCluster();
      };

      const updatePassengers = function (passengers, passengerIcon) {
        const keepIds = new Set();

        passengers.forEach(function (passenger) {
          const id = String(passenger.id);
          keepIds.add(id);
          const target = { lat: Number(passenger.lat), lng: Number(passenger.lng) };

          if (!passengerMarkers[id]) {
            passengerMarkers[id] = new google.maps.Marker({
              position: target,
              map: state.visibility.passengers ? state.map : null,
              icon: passengerIcon,
              title: 'Passenger #' + passenger.id,
            });
          } else {
            passengerMarkers[id].setPosition(target);
            passengerMarkers[id].setTitle('Passenger #' + passenger.id);
            passengerMarkers[id].setMap(state.visibility.passengers ? state.map : null);
          }
        });

        removeMarkersNotInSet(passengerMarkers, keepIds);
      };

      const updateRides = function (rides) {
        const keepIds = new Set();

        rides.forEach(function (ride) {
          const rideId = String(ride.id || (ride.driver_lat + ':' + ride.driver_lng + ':' + ride.passenger_lat + ':' + ride.passenger_lng));
          keepIds.add(rideId);
          const routeKey = [
            Number(ride.driver_lat).toFixed(5),
            Number(ride.driver_lng).toFixed(5),
            Number(ride.passenger_lat).toFixed(5),
            Number(ride.passenger_lng).toFixed(5),
          ].join(':');

          if (!ridePolylines[rideId]) {
            ridePolylines[rideId] = new google.maps.DirectionsRenderer({
              map: state.visibility.rides ? state.map : null,
              suppressMarkers: true,
              preserveViewport: true,
              polylineOptions: {
                strokeColor: '#f97316',
                strokeOpacity: 0.82,
                strokeWeight: 4,
              },
            });
            ridePolylines[rideId].__lastRouteKey = '';
          }

          const renderer = ridePolylines[rideId];
          renderer.setMap(state.visibility.rides ? state.map : null);

          if (!state.directionsService) {
            return;
          }

          if (renderer.__lastRouteKey === routeKey) {
            return;
          }

          renderer.__lastRouteKey = routeKey;

          state.directionsService.route(
            {
              origin: { lat: Number(ride.driver_lat), lng: Number(ride.driver_lng) },
              destination: { lat: Number(ride.passenger_lat), lng: Number(ride.passenger_lng) },
              travelMode: google.maps.TravelMode.DRIVING,
            },
            function (result, status) {
              if (status === 'OK' && result) {
                renderer.setDirections(result);
              }
            }
          );
        });

        Object.keys(ridePolylines).forEach(function (id) {
          if (!keepIds.has(id)) {
            ridePolylines[id].setMap(null);
            delete ridePolylines[id];
          }
        });
      };

      const fetchMapData = function () {
        return fetchJson(endpoint)
          .then(function (payload) {
            const drivers = Array.isArray(payload.drivers) ? payload.drivers : [];
            const passengers = Array.isArray(payload.passengers) ? payload.passengers : [];
            const rides = Array.isArray(payload.rides) ? payload.rides : [];
            const activeDriverCount = Number(payload.active_driver_count ?? drivers.filter(function (d) { return !!d.is_active; }).length);
            const activeRideCount = Number(payload.active_ride_count ?? rides.length);

            state.latestSnapshot.drivers = drivers;
            state.latestSnapshot.passengers = passengers;
            state.latestSnapshot.rides = rides;
            state.latestSnapshot.activeDriverCount = activeDriverCount;
            state.latestSnapshot.activeRideCount = activeRideCount;

            if (activeDriversCounter) {
              activeDriversCounter.textContent = 'Active Drivers: ' + activeDriverCount;
            }

            if (mapMetrics) {
              const metrics = {
                drivers: 'Drivers: ' + drivers.length,
                passengers: 'Passengers: ' + passengers.length,
                rides: 'Active Rides: ' + activeRideCount,
              };

              mapMetrics.querySelectorAll('[data-metric]').forEach(function (node) {
                const key = node.getAttribute('data-metric');
                if (key && metrics[key]) {
                  node.textContent = metrics[key];
                }
              });
            }

            hideStatus();

            updateDrivers(drivers, state.icons.car);
            updatePassengers(passengers, state.icons.passenger);
            updateRides(rides);
          })
          .catch(function (error) {
            showStatus(error.message + '. Please ensure admin API auth/session is valid.');
          });
      };

      const fetchDemandHeatmap = function () {
        return fetchJson(demandEndpoint)
          .then(function (payload) {
            hideStatus();
            return payload;
          })
          .then(function (payload) {
            const points = Array.isArray(payload.points) ? payload.points : [];
            state.latestSnapshot.heatmapPoints = points.length;
            const weighted = points.map(function (point) {
              return {
                location: new google.maps.LatLng(Number(point.lat), Number(point.lng)),
                weight: Number(point.intensity || 0.2),
              };
            });

            if (!state.heatmapLayer) {
              state.heatmapLayer = new google.maps.visualization.HeatmapLayer({
                data: weighted,
                dissipating: true,
                radius: 36,
                opacity: 0.65,
                gradient: [
                  'rgba(34,197,94,0)',
                  'rgba(34,197,94,1)',
                  'rgba(250,204,21,1)',
                  'rgba(239,68,68,1)'
                ],
              });
            } else {
              state.heatmapLayer.setData(weighted);
            }

            state.heatmapLayer.setMap(state.visibility.heatmap ? state.map : null);
          })
          .catch(function () {
            // Ignore API errors to keep map controls responsive.
          });
      };

      const createLiveRequestMarker = function (request) {
        const marker = new google.maps.Marker({
          position: { lat: Number(request.pickup_lat), lng: Number(request.pickup_lng) },
          map: state.map,
          title: 'Request #' + request.id,
          icon: {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#eab308"><path d="M12 2a7 7 0 00-7 7c0 4.99 7 13 7 13s7-8.01 7-13a7 7 0 00-7-7zm0 9.5A2.5 2.5 0 1112 6a2.5 2.5 0 010 5.5z"/></svg>'),
            scaledSize: new google.maps.Size(30, 30),
          },
          animation: google.maps.Animation.BOUNCE,
        });

        setTimeout(function () {
          marker.setAnimation(null);
        }, 2400);

        return marker;
      };

      const updateLiveRequests = function () {
        return fetchJson(liveRequestsEndpoint)
          .then(function (payload) {
            hideStatus();
            return payload;
          })
          .then(function (payload) {
            const requests = Array.isArray(payload.requests) ? payload.requests : [];
            state.latestSnapshot.liveRequests = requests;
            const seenIds = new Set();

            requests.forEach(function (request) {
              const id = String(request.id);
              seenIds.add(id);

              if (!state.requestMarkers[id]) {
                const marker = createLiveRequestMarker(request);
                const infoWindow = new google.maps.InfoWindow({
                  content:
                    '<div style="min-width:210px">' +
                    '<div><strong>Passenger:</strong> ' + (request.passenger_name || 'Unknown') + '</div>' +
                    '<div><strong>Pickup:</strong> ' + Number(request.pickup_lat).toFixed(4) + ', ' + Number(request.pickup_lng).toFixed(4) + '</div>' +
                    '<div><strong>Destination:</strong> ' + Number(request.destination_lat).toFixed(4) + ', ' + Number(request.destination_lng).toFixed(4) + '</div>' +
                    '</div>',
                });

                infoWindow.open({ map: state.map, anchor: marker });
                state.requestMarkers[id] = marker;
                state.requestWindows[id] = infoWindow;
              }
            });

            Object.keys(state.requestMarkers).forEach(function (id) {
              if (!seenIds.has(id)) {
                state.requestMarkers[id].setMap(null);
                delete state.requestMarkers[id];

                if (state.requestWindows[id]) {
                  state.requestWindows[id].close();
                  delete state.requestWindows[id];
                }
              }
            });
          })
          .catch(function () {
            // Ignore transient live-requests API failures.
          });
      };

      const pauseReplay = function () {
        if (state.replay.timer) {
          clearInterval(state.replay.timer);
          state.replay.timer = null;
        }
      };

      const playReplay = function () {
        if (!state.replay.points.length || state.replay.timer) {
          return;
        }

        const tickMs = Math.max(120, Math.floor(700 / state.replay.speed));
        state.replay.timer = setInterval(function () {
          state.replay.index += 1;
          if (state.replay.index >= state.replay.points.length) {
            pauseReplay();
            return;
          }

          const point = state.replay.points[state.replay.index];
          if (state.replay.marker) {
            state.replay.marker.setPosition(point);
          }
        }, tickMs);
      };

      const loadReplayRide = function (rideId) {
        const url = routeHistoryTemplate.replace('__RIDE__', encodeURIComponent(rideId));

        return fetch(url, { cache: 'no-store', credentials: 'same-origin' })
          .then(function (response) { return response.json(); })
          .then(function (payload) {
            const coordinates = Array.isArray(payload.coordinates) ? payload.coordinates : [];
            if (!coordinates.length) {
              return;
            }

            pauseReplay();
            state.replay.points = coordinates.map(function (point) {
              return { lat: Number(point.lat), lng: Number(point.lng) };
            });
            state.replay.index = 0;

            if (state.replay.polyline) {
              state.replay.polyline.setMap(null);
            }

            state.replay.polyline = new google.maps.Polyline({
              path: state.replay.points,
              geodesic: true,
              strokeColor: '#2563eb',
              strokeOpacity: 0.85,
              strokeWeight: 4,
              map: state.map,
            });

            if (state.replay.marker) {
              state.replay.marker.setMap(null);
            }

            state.replay.marker = new google.maps.Marker({
              position: state.replay.points[0],
              map: state.map,
              title: 'Replay Marker',
              icon: {
                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#2563eb"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm-2 5l7 5-7 5V7z"/></svg>'),
                scaledSize: new google.maps.Size(30, 30),
              },
            });

            playReplay();
          })
          .catch(function () {
            // Ignore replay load failures.
          });
      };

      const bindControls = function () {
        const setToggleState = function (button, isOn) {
          if (!button) {
            return;
          }

          button.classList.toggle('bg-green-600', isOn);
          button.classList.toggle('text-white', isOn);
          button.classList.toggle('border-green-600', isOn);
        };

        setToggleState(toggleDriversBtn, state.visibility.drivers);
        setToggleState(togglePassengersBtn, state.visibility.passengers);
        setToggleState(toggleHeatmapBtn, state.visibility.heatmap);
        setToggleState(toggleRidesBtn, state.visibility.rides);

        if (popupCloseBtn && popupPanel) {
          popupCloseBtn.addEventListener('click', function () {
            popupPanel.classList.add('hidden');
          });
        }

        toggleDriversBtn.addEventListener('click', function () {
          state.visibility.drivers = !state.visibility.drivers;
          setToggleState(toggleDriversBtn, state.visibility.drivers);
          Object.values(driverMarkers).forEach(function (marker) {
            marker.setMap(state.visibility.drivers ? state.map : null);
          });
          updateDriverCluster();
          showStatus('Drivers layer ' + (state.visibility.drivers ? 'enabled' : 'hidden') + '.');
          showDataPopup('Drivers Layer', [
            'Visible: ' + (state.visibility.drivers ? 'Yes' : 'No'),
            'Tracked Drivers: ' + state.latestSnapshot.drivers.length,
            'Active Drivers: ' + state.latestSnapshot.activeDriverCount,
            'Top Driver IDs: ' + (state.latestSnapshot.drivers.slice(0, 3).map(function (d) { return d.id; }).join(', ') || 'None'),
          ]);
        });

        togglePassengersBtn.addEventListener('click', function () {
          state.visibility.passengers = !state.visibility.passengers;
          setToggleState(togglePassengersBtn, state.visibility.passengers);
          Object.values(passengerMarkers).forEach(function (marker) {
            marker.setMap(state.visibility.passengers ? state.map : null);
          });
          showStatus('Passengers layer ' + (state.visibility.passengers ? 'enabled' : 'hidden') + '.');
          showDataPopup('Passengers Layer', [
            'Visible: ' + (state.visibility.passengers ? 'Yes' : 'No'),
            'Passengers on Map: ' + state.latestSnapshot.passengers.length,
            'Top Passenger IDs: ' + (state.latestSnapshot.passengers.slice(0, 3).map(function (p) { return p.id; }).join(', ') || 'None'),
            'Live Request IDs: ' + (state.latestSnapshot.liveRequests.slice(0, 3).map(function (r) { return r.id; }).join(', ') || 'None'),
          ]);
        });

        toggleHeatmapBtn.addEventListener('click', function () {
          state.visibility.heatmap = !state.visibility.heatmap;
          setToggleState(toggleHeatmapBtn, state.visibility.heatmap);
          if (state.visibility.heatmap) {
            fetchDemandHeatmap();
          } else if (state.heatmapLayer) {
            state.heatmapLayer.setMap(null);
          }
          showStatus('Demand heatmap ' + (state.visibility.heatmap ? 'enabled' : 'hidden') + '.');
          showDataPopup('Demand Heatmap', [
            'Visible: ' + (state.visibility.heatmap ? 'Yes' : 'No'),
            'Prediction Points: ' + state.latestSnapshot.heatmapPoints,
            'Tracked Area: Kigali urban grid',
          ]);
        });

        toggleRidesBtn.addEventListener('click', function () {
          state.visibility.rides = !state.visibility.rides;
          setToggleState(toggleRidesBtn, state.visibility.rides);
          Object.values(ridePolylines).forEach(function (polyline) {
            polyline.setMap(state.visibility.rides ? state.map : null);
          });
          showStatus('Active rides layer ' + (state.visibility.rides ? 'enabled' : 'hidden') + '.');
          showDataPopup('Active Rides Layer', [
            'Visible: ' + (state.visibility.rides ? 'Yes' : 'No'),
            'Active Rides: ' + state.latestSnapshot.activeRideCount,
            'Rendered Routes: ' + Object.keys(ridePolylines).length,
            'Top Ride IDs: ' + (state.latestSnapshot.rides.slice(0, 3).map(function (r) { return r.id; }).join(', ') || 'None'),
          ]);
        });

        replayRideBtn.addEventListener('click', function () {
          const rideId = window.prompt('Enter completed ride ID to replay:');
          if (!rideId) {
            return;
          }

          loadReplayRide(rideId);
          showDataPopup('Replay Requested', [
            'Ride ID: ' + rideId,
            'Speed: ' + state.replay.speed + 'x',
            'Active Ride IDs: ' + (state.latestSnapshot.rides.slice(0, 5).map(function (r) { return r.id; }).join(', ') || 'None'),
          ]);
        });

        replayPlayBtn.addEventListener('click', function () {
          playReplay();
          showDataPopup('Replay', [
            'State: Playing',
            'Speed: ' + state.replay.speed + 'x',
            'Route Points: ' + state.replay.points.length,
          ]);
        });

        replayPauseBtn.addEventListener('click', function () {
          pauseReplay();
          showDataPopup('Replay', [
            'State: Paused',
            'Current Point: ' + state.replay.index,
          ]);
        });

        replaySpeedSelect.addEventListener('change', function () {
          state.replay.speed = Number(replaySpeedSelect.value || '1');
          if (state.replay.timer) {
            pauseReplay();
            playReplay();
          }

          showDataPopup('Replay Speed Updated', [
            'Speed: ' + state.replay.speed + 'x',
          ]);
        });
      };

      loadGoogleMapsScript(apiKey)
        .then(function () {
          state.map = new google.maps.Map(mapRoot, {
            center: kigaliCenter,
            zoom: 13,
            streetViewControl: false,
            mapTypeControl: false,
          });

          window.addEventListener('resize', function () {
            if (!state.map) {
              return;
            }

            google.maps.event.trigger(state.map, 'resize');
            state.map.setCenter(kigaliCenter);
          });

          state.directionsService = new google.maps.DirectionsService();

          state.icons = {
            car: {
              url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#16a34a"><path d="M18.92 6c-.17-.58-.7-1-1.3-1H6.38c-.6 0-1.13.42-1.3 1L3 13v8h2v-2h14v2h2v-8l-2.08-7zM6.8 7h10.4l1.2 4H5.6l1.2-4zM7 16a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm10 0a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/></svg>'),
              scaledSize: new google.maps.Size(28, 28),
            },
            passenger: {
              url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#2563eb"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4.42 0-8 2.24-8 5v3h16v-3c0-2.76-3.58-5-8-5z"/></svg>'),
              scaledSize: new google.maps.Size(28, 28),
            },
          };

          bindControls();
          fetchMapData();
          updateLiveRequests();
          connectSupabaseRealtime();

          if (state.refreshTimer) {
            clearInterval(state.refreshTimer);
          }

          if (state.requestsTimer) {
            clearInterval(state.requestsTimer);
          }

          state.refreshTimer = setInterval(fetchMapData, 5000);
          state.requestsTimer = setInterval(updateLiveRequests, refreshMs);

          if (state.visibility.heatmap) {
            fetchDemandHeatmap();
          }
        })
        .catch(function () {
          mapRoot.innerHTML = '<div class="h-full w-full flex items-center justify-center text-sm text-red-600">Google Map failed to load. Check key restrictions (HTTP referrer), billing, and enabled APIs.</div>';
        });
    })();
  </script>
</div>
