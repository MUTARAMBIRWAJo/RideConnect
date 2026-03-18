const globalState = window;

globalState.initMap = globalState.initMap || function initMapCallback() {
    // Required callback for Google Maps script URL.
    // Individual widgets/pages own their map rendering logic.
    console.log('Google Maps callback executed:', typeof globalState.google !== 'undefined');
};

/**
 * Load Google Maps JS API exactly once and keep a shared promise.
 * Widgets can call this helper from inline scripts in Filament pages.
 */
globalState.loadGoogleMapsScript = function loadGoogleMapsScript(apiKey, callbackName = 'initMap') {
    if (globalState.google && globalState.google.maps && globalState.google.maps.visualization) {
        console.log('Google Maps already loaded with visualization library.');
        return Promise.resolve(globalState.google);
    }

    if (globalState.__rideConnectGoogleMapsPromise) {
        return globalState.__rideConnectGoogleMapsPromise;
    }

    globalState.__rideConnectGoogleMapsPromise = new Promise((resolve, reject) => {
        if (!apiKey) {
            reject(new Error('Missing GOOGLE_MAPS_API_KEY'));
            return;
        }

        // Google triggers this hook when key restrictions/billing/API enablement fail.
        globalState.gm_authFailure = function gmAuthFailure() {
            reject(new Error('Google Maps authentication failed (403 or restricted key).'));
        };

        const script = document.createElement('script');
        script.async = true;
        script.defer = true;
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&libraries=visualization,places&v=weekly&callback=${encodeURIComponent(callbackName)}`;
        script.onload = () => {
            console.log('Google Maps Loaded:', typeof globalState.google !== 'undefined');

            if (!globalState.google || !globalState.google.maps) {
                reject(new Error('Google Maps script loaded but window.google.maps is undefined.'));
                return;
            }

            resolve(globalState.google);
        };
        script.onerror = () => reject(new Error('Failed to load Google Maps script.'));

        document.head.appendChild(script);
    });

    return globalState.__rideConnectGoogleMapsPromise;
};
