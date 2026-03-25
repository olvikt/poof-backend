<div></div>

<script>
(() => {
    const state = window.__poofCourierTrackerState ?? {
        watchId: null,
        bootstrapped: false,
    };

    window.__poofCourierTrackerState = state;

    function userIsCourier() {
        return @json(auth()->user()?->isCourier() ?? false);
    }

    function userIsOnline() {
        return @json(auth()->user()?->isCourierOnline() ?? false);
    }

    function start() {
        if (state.watchId !== null || !userIsCourier()) {
            return;
        }

        if (!navigator.geolocation) {
            console.warn('Geolocation not supported');
            return;
        }

        state.watchId = navigator.geolocation.watchPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                const accuracy = pos.coords.accuracy ?? null;

                const coordsValid = Number.isFinite(Number(lat))
                    && Number.isFinite(Number(lng))
                    && Math.abs(Number(lat)) <= 90
                    && Math.abs(Number(lng)) <= 180
                    && !(Number(lat) === 0 && Number(lng) === 0);

                const accuracyValid = !Number.isFinite(Number(accuracy)) || Number(accuracy) <= 120;
                const courierConfirmed = coordsValid && accuracyValid;

                if (!coordsValid) {
                    return;
                }

                if (courierConfirmed && window.Livewire?.dispatch) {
                    window.Livewire.dispatch('courier-location', { lat, lng, accuracy });
                }

                window.dispatchEvent(new CustomEvent('map:courier-update', {
                    detail: {
                        courierLat: lat,
                        courierLng: lng,
                        accuracy,
                        courierConfirmed,
                        source: 'tracker-watchPosition',
                        radiusKm: 5,
                    }
                }));
            },
            (err) => console.warn('Geolocation error', err),
            { enableHighAccuracy: true, maximumAge: 5000, timeout: 10000 }
        );
    }

    function stop() {
        if (state.watchId === null) {
            return;
        }

        try {
            navigator.geolocation.clearWatch(state.watchId);
        } catch (e) {
            console.warn('Failed to clear geolocation watch', e);
        }

        state.watchId = null;
    }

    function bootstrap() {
        if (userIsOnline()) {
            start();
        }
    }

    if (!state.bootstrapped) {
        window.addEventListener('courier:online', start);
        window.addEventListener('courier:offline', stop);
        window.addEventListener('courier:tracker-ready', bootstrap);
        window.addEventListener('livewire:navigated', bootstrap);
        state.bootstrapped = true;
    }

    bootstrap();
})();
</script>
