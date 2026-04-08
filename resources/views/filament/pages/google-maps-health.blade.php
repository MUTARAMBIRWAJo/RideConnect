<x-filament-panels::page>
    <div class="space-y-4" wire:poll.15s>
        <section
            id="gmaps-health-panel"
            class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
            data-api-key="{{ $googleMapsHealth['api_key'] }}"
            data-has-key="{{ $googleMapsHealth['has_key'] ? '1' : '0' }}"
            data-config-source="{{ $googleMapsHealth['config_source'] }}"
            data-preflight-url="{{ $googleMapsHealth['preflight_url'] }}"
            data-live-map-url="{{ $googleMapsHealth['live_map_url'] }}"
        >
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Map Checkup</h2>
                    <p class="text-sm text-gray-600">This page explains map issues in simple terms and suggests what to do next.</p>
                </div>

                <button
                    type="button"
                    id="gmaps-run-check"
                    class="inline-flex items-center rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-black"
                >
                    Check Again
                </button>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-gray-200 p-3">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Map Access Code</p>
                    <p id="gmaps-key-status" class="mt-1 text-sm font-semibold text-gray-900">Pending</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Where Settings Come From</p>
                    <p id="gmaps-config-source" class="mt-1 text-sm font-semibold text-gray-900">Pending</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Map Screen Loading</p>
                    <p id="gmaps-script-status" class="mt-1 text-sm font-semibold text-gray-900">Pending</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Live Trip Data</p>
                    <p id="gmaps-live-status" class="mt-1 text-sm font-semibold text-gray-900">Pending</p>
                </div>
            </div>

            <p id="gmaps-health-note" class="mt-3 text-sm text-gray-600">Ready to check map status.</p>

            <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
                <h3 class="text-sm font-semibold text-blue-900">What these results mean</h3>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-blue-900">
                    <li>If Map Access Code says <span class="font-semibold">No</span>, the system cannot open the map service.</li>
                    <li>If Map Screen Loading shows an error, users may see a blank map or no pins.</li>
                    <li>If Live Trip Data fails, map can open but active rides may not appear.</li>
                    <li>If everything says OK, the map is healthy for both staff and riders.</li>
                </ul>
            </div>

            <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
                <h3 class="text-sm font-semibold text-gray-900">Simple action guide</h3>
                <div class="mt-2 grid gap-2 md:grid-cols-2">
                    <div class="rounded-md border border-gray-200 bg-white p-3 text-sm text-gray-700">
                        <p class="font-semibold text-gray-900">"No" on Map Access Code</p>
                        <p class="mt-1">Add or fix the map key in your environment settings, then check again.</p>
                    </div>
                    <div class="rounded-md border border-gray-200 bg-white p-3 text-sm text-gray-700">
                        <p class="font-semibold text-gray-900">Map screen does not load</p>
                        <p class="mt-1">Make sure your allowed website list includes this site address.</p>
                    </div>
                    <div class="rounded-md border border-gray-200 bg-white p-3 text-sm text-gray-700">
                        <p class="font-semibold text-gray-900">Live Trip Data shows error</p>
                        <p class="mt-1">Check backend service health and database connectivity.</p>
                    </div>
                    <div class="rounded-md border border-gray-200 bg-white p-3 text-sm text-gray-700">
                        <p class="font-semibold text-gray-900">Still not fixed</p>
                        <p class="mt-1">Share this page screenshot with your support/admin team.</p>
                    </div>
                </div>
            </div>

            <div id="gmaps-referrer-help" class="mt-3 hidden rounded-lg border border-amber-200 bg-amber-50 p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Copy Allowed Website Rules</p>
                    <button
                        type="button"
                        id="gmaps-copy-referrers"
                        class="inline-flex items-center rounded-md border border-amber-300 bg-white px-2.5 py-1 text-xs font-medium text-amber-900 hover:bg-amber-100"
                    >
                        Copy Rules
                    </button>
                </div>
                <p class="mt-1 text-xs text-amber-900">Add these website addresses in your Google Maps key settings so this app is allowed to load maps.</p>
                <textarea
                    id="gmaps-referrer-rules"
                    readonly
                    rows="6"
                    class="mt-2 w-full rounded-md border border-amber-300 bg-white p-2 font-mono text-xs text-amber-900"
                ></textarea>
            </div>

            <div id="gmaps-health-map" class="mt-4 h-72 w-full rounded-lg border border-gray-200" style="height: 320px; min-height: 320px;"></div>
        </section>
    </div>

    <script>
        (function () {
            const panel = document.getElementById('gmaps-health-panel');
            if (!panel) {
                return;
            }

            const runButton = document.getElementById('gmaps-run-check');
            const keyStatusEl = document.getElementById('gmaps-key-status');
            const configSourceEl = document.getElementById('gmaps-config-source');
            const scriptStatusEl = document.getElementById('gmaps-script-status');
            const liveStatusEl = document.getElementById('gmaps-live-status');
            const noteEl = document.getElementById('gmaps-health-note');
            const mapEl = document.getElementById('gmaps-health-map');
            const referrerHelpEl = document.getElementById('gmaps-referrer-help');
            const referrerRulesEl = document.getElementById('gmaps-referrer-rules');
            const copyReferrersButton = document.getElementById('gmaps-copy-referrers');

            const apiKey = panel.dataset.apiKey || '';
            const hasKey = panel.dataset.hasKey === '1';
            const configSource = panel.dataset.configSource || 'missing';
            const preflightUrl = panel.dataset.preflightUrl;
            const liveMapUrl = panel.dataset.liveMapUrl;

            const getReferrerRules = () => {
                const origin = window.location.origin;

                return [
                    'http://localhost:8000/*',
                    'http://127.0.0.1:8000/*',
                    origin + '/*',
                    'https://rideconnect-emp0.onrender.com/*',
                    'https://*.onrender.com/*',
                ].filter((value, index, list) => list.indexOf(value) === index);
            };

            const showReferrerHelp = () => {
                if (!referrerHelpEl || !referrerRulesEl) {
                    return;
                }

                referrerRulesEl.value = getReferrerRules().join('\n');
                referrerHelpEl.classList.remove('hidden');
            };

            const hideReferrerHelp = () => {
                if (!referrerHelpEl) {
                    return;
                }

                referrerHelpEl.classList.add('hidden');
            };

            copyReferrersButton?.addEventListener('click', async () => {
                if (!referrerRulesEl) {
                    return;
                }

                try {
                    await navigator.clipboard.writeText(referrerRulesEl.value);
                    noteEl.textContent = 'Allowed website rules copied.';
                } catch (error) {
                    referrerRulesEl.focus();
                    referrerRulesEl.select();
                    noteEl.textContent = 'Clipboard is blocked. Rules are selected, so you can copy manually.';
                }
            });

            const setState = (el, text, isOk) => {
                el.textContent = text;
                el.classList.remove('text-emerald-600', 'text-rose-600', 'text-gray-900');
                el.classList.add(isOk === null ? 'text-gray-900' : (isOk ? 'text-emerald-600' : 'text-rose-600'));
            };

            const loadGoogleScript = async () => {
                if (window.google && window.google.maps) {
                    return true;
                }

                if (typeof window.loadGoogleMapsScript === 'function') {
                    await window.loadGoogleMapsScript(apiKey, 'initMap');
                    return !!(window.google && window.google.maps);
                }

                await new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&libraries=visualization`;
                    script.async = true;
                    script.defer = true;
                    script.onload = () => resolve();
                    script.onerror = () => reject(new Error('Failed to load script'));
                    document.head.appendChild(script);
                });

                return !!(window.google && window.google.maps);
            };

            const runPreflight = async () => {
                setState(keyStatusEl, hasKey ? 'Yes' : 'No', hasKey);
                setState(configSourceEl, configSource, configSource !== 'missing');
                setState(scriptStatusEl, 'Checking...', null);
                setState(liveStatusEl, 'Checking...', null);
                noteEl.textContent = 'Checking map health...';
                hideReferrerHelp();

                try {
                    const response = await fetch(preflightUrl, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });

                    if (response.ok) {
                        const payload = await response.json();
                        const checks = payload.checks || {};
                        setState(keyStatusEl, checks.key_present ? 'Yes' : 'No', !!checks.key_present);
                        setState(configSourceEl, checks.config_source || 'missing', (checks.config_source || 'missing') !== 'missing');
                    }
                } catch (error) {
                    setState(configSourceEl, 'Unable to read settings', false);
                }

                try {
                    if (!hasKey) {
                        throw new Error('Map access code is missing');
                    }

                    const loaded = await loadGoogleScript();
                    if (!loaded) {
                        throw new Error('Map service did not finish loading');
                    }

                    setState(scriptStatusEl, 'Loaded', true);

                    if (mapEl) {
                        const map = new window.google.maps.Map(mapEl, {
                            center: { lat: -1.9441, lng: 30.0619 },
                            zoom: 11,
                            mapTypeControl: false,
                            streetViewControl: false,
                            fullscreenControl: false,
                        });

                        new window.google.maps.Marker({
                            map,
                            position: { lat: -1.9441, lng: 30.0619 },
                            title: 'Kigali Center',
                        });
                    }
                } catch (error) {
                    const scriptError = (error && error.message ? error.message : 'Failed');
                    setState(scriptStatusEl, scriptError, false);

                    const lowerScriptError = String(scriptError).toLowerCase();
                    const shouldShowRules =
                        lowerScriptError.includes('403') ||
                        lowerScriptError.includes('auth') ||
                        lowerScriptError.includes('referer') ||
                        lowerScriptError.includes('restricted');

                    if (shouldShowRules) {
                        showReferrerHelp();
                    }
                }

                try {
                    const response = await fetch(liveMapUrl, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        setState(liveStatusEl, `Service unavailable (${response.status})`, false);
                    } else {
                        const payload = await response.json();
                        setState(liveStatusEl, payload.success ? 'OK' : 'Unexpected payload', !!payload.success);
                    }
                } catch (error) {
                    setState(liveStatusEl, error.message || 'Unable to load live trip data', false);
                }

                noteEl.textContent = 'Check completed. See the simple action guide above if any item failed.';
            };

            runButton?.addEventListener('click', runPreflight);
            runPreflight();
        })();
    </script>
</x-filament-panels::page>
