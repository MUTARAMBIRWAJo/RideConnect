<x-filament-panels::page>
    <div class="space-y-4">
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
                    <h2 class="text-base font-semibold text-gray-900">Google Maps Preflight</h2>
                    <p class="text-sm text-gray-600">Checks key presence, config source, script loading, and live-map endpoint in one panel.</p>
                </div>

                <button
                    type="button"
                    id="gmaps-run-check"
                    class="inline-flex items-center rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-black"
                >
                    Run Preflight
                </button>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-gray-200 p-3">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Key Present</p>
                    <p id="gmaps-key-status" class="mt-1 text-sm font-semibold text-gray-900">Pending</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Config Source</p>
                    <p id="gmaps-config-source" class="mt-1 text-sm font-semibold text-gray-900">Pending</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Script Load Status</p>
                    <p id="gmaps-script-status" class="mt-1 text-sm font-semibold text-gray-900">Pending</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Live Map Endpoint</p>
                    <p id="gmaps-live-status" class="mt-1 text-sm font-semibold text-gray-900">Pending</p>
                </div>
            </div>

            <p id="gmaps-health-note" class="mt-3 text-sm text-gray-600">Ready to run checks.</p>

            <div id="gmaps-referrer-help" class="mt-3 hidden rounded-lg border border-amber-200 bg-amber-50 p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Copy Referrer Rules</p>
                    <button
                        type="button"
                        id="gmaps-copy-referrers"
                        class="inline-flex items-center rounded-md border border-amber-300 bg-white px-2.5 py-1 text-xs font-medium text-amber-900 hover:bg-amber-100"
                    >
                        Copy Rules
                    </button>
                </div>
                <p class="mt-1 text-xs text-amber-900">Add these HTTP referrer entries to your Google Maps browser key restrictions.</p>
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
                    noteEl.textContent = 'Referrer rules copied to clipboard.';
                } catch (error) {
                    referrerRulesEl.focus();
                    referrerRulesEl.select();
                    noteEl.textContent = 'Unable to access clipboard. Referrer rules selected for manual copy.';
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
                noteEl.textContent = 'Running checks...';
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
                    setState(configSourceEl, 'preflight endpoint error', false);
                }

                try {
                    if (!hasKey) {
                        throw new Error('API key missing');
                    }

                    const loaded = await loadGoogleScript();
                    if (!loaded) {
                        throw new Error('Script loaded but google.maps unavailable');
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
                        setState(liveStatusEl, `HTTP ${response.status}`, false);
                    } else {
                        const payload = await response.json();
                        setState(liveStatusEl, payload.success ? 'OK' : 'Unexpected payload', !!payload.success);
                    }
                } catch (error) {
                    setState(liveStatusEl, error.message || 'Request failed', false);
                }

                noteEl.textContent = 'Preflight completed.';
            };

            runButton?.addEventListener('click', runPreflight);
            runPreflight();
        })();
    </script>
</x-filament-panels::page>
