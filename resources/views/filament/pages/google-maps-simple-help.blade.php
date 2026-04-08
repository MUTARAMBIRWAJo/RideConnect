<x-filament-panels::page>
    <div class="space-y-4" wire:poll.20s>
        <section
            id="gmaps-simple-help-panel"
            class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
            data-has-key="{{ $simpleMapHelp['has_key'] ? '1' : '0' }}"
            data-preflight-url="{{ $simpleMapHelp['preflight_url'] }}"
            data-live-map-url="{{ $simpleMapHelp['live_map_url'] }}"
        >
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Map Help (Easy)</h2>
                    <p class="text-sm text-gray-600">Quick explanation in simple English and Kinyarwanda.</p>
                </div>

                <button
                    type="button"
                    id="gmaps-simple-run-check"
                    class="inline-flex items-center rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-black"
                >
                    Check Now
                </button>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-gray-200 p-3">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Map Access</p>
                    <p id="gmaps-simple-key" class="mt-1 text-sm font-semibold text-gray-900">Checking...</p>
                    <p class="mt-1 text-xs text-gray-600">EN: Can we open maps? | RW: Ese karita irafunguka?</p>
                </div>

                <div class="rounded-lg border border-gray-200 p-3">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Map Screen</p>
                    <p id="gmaps-simple-script" class="mt-1 text-sm font-semibold text-gray-900">Checking...</p>
                    <p class="mt-1 text-xs text-gray-600">EN: Map visible? | RW: Karita iragaragara?</p>
                </div>

                <div class="rounded-lg border border-gray-200 p-3">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Live Rides</p>
                    <p id="gmaps-simple-live" class="mt-1 text-sm font-semibold text-gray-900">Checking...</p>
                    <p class="mt-1 text-xs text-gray-600">EN: Trips updating? | RW: Ingendo ziravugururwa?</p>
                </div>
            </div>

            <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                <p class="font-semibold">If you see a red status:</p>
                <p class="mt-1">EN: Share a screenshot with admin support and mention which box is red.</p>
                <p class="mt-1">RW: Fata ifoto ya screen uyisangize admin support, uvuge agasanduku kari umutuku.</p>
            </div>

            <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                <p class="font-semibold text-gray-900">What users may feel / Icyo abakoresha babona</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>EN: Blank map area. | RW: Ahagaragara karita hasigara hameze ubusa.</li>
                    <li>EN: Drivers not appearing. | RW: Abatwara ntibagaragara kuri karita.</li>
                    <li>EN: Delayed location updates. | RW: Kuvugurura aho umuntu ari biratinda.</li>
                </ul>
            </div>

            <p id="gmaps-simple-note" class="mt-3 text-sm text-gray-600">Running quick check...</p>
        </section>
    </div>

    <script>
        (function () {
            const panel = document.getElementById('gmaps-simple-help-panel');
            if (!panel) {
                return;
            }

            const runButton = document.getElementById('gmaps-simple-run-check');
            const keyEl = document.getElementById('gmaps-simple-key');
            const scriptEl = document.getElementById('gmaps-simple-script');
            const liveEl = document.getElementById('gmaps-simple-live');
            const noteEl = document.getElementById('gmaps-simple-note');

            const hasKey = panel.dataset.hasKey === '1';
            const preflightUrl = panel.dataset.preflightUrl;
            const liveMapUrl = panel.dataset.liveMapUrl;

            const setState = (el, text, ok) => {
                el.textContent = text;
                el.classList.remove('text-emerald-600', 'text-rose-600', 'text-gray-900');
                el.classList.add(ok === null ? 'text-gray-900' : (ok ? 'text-emerald-600' : 'text-rose-600'));
            };

            const runCheck = async () => {
                noteEl.textContent = 'Checking map status...';
                setState(keyEl, 'Checking...', null);
                setState(scriptEl, 'Checking...', null);
                setState(liveEl, 'Checking...', null);

                try {
                    const response = await fetch(preflightUrl, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        setState(keyEl, 'Needs admin check', false);
                    } else {
                        const payload = await response.json();
                        const present = !!(payload.checks && payload.checks.key_present);
                        setState(keyEl, present ? 'OK' : 'Missing', present);
                    }
                } catch (error) {
                    setState(keyEl, 'Needs admin check', false);
                }

                if (hasKey) {
                    setState(scriptEl, 'Likely OK', true);
                } else {
                    setState(scriptEl, 'May fail', false);
                }

                try {
                    const response = await fetch(liveMapUrl, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        setState(liveEl, 'Not available', false);
                    } else {
                        const payload = await response.json();
                        setState(liveEl, payload.success ? 'OK' : 'Needs check', !!payload.success);
                    }
                } catch (error) {
                    setState(liveEl, 'Not available', false);
                }

                noteEl.textContent = 'Check done. If any red status appears, share screenshot with support.';
            };

            runButton?.addEventListener('click', runCheck);
            runCheck();
        })();
    </script>
</x-filament-panels::page>
