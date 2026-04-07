<div></div>

<script>
(() => {
    const state = window.__poofCourierTrackerState ?? {
        watchId: null,
        bootstrapped: false,
        online: null,
        bootstrapAttempts: 0,
        bootstrapTimer: null,
        firstPayloadReceived: false,
    };

    window.__poofCourierTrackerState = state;

    function emitMarker(event, detail = {}, level = 'info') {
        const payload = {
            event,
            level,
            ts: Date.now(),
            ...detail,
        };

        window.dispatchEvent(new CustomEvent('poof:courier-geo-marker', { detail: payload }));

        const label = `[POOF:courier-tracker][${level}] ${event}`;
        if (level === 'error') {
            console.error(label, payload);
        } else if (level === 'warn') {
            console.warn(label, payload);
        } else {
            console.info(label, payload);
        }
    }

    function userIsCourier() {
        return @json(auth()->user()?->isCourier() ?? false);
    }

    const runtimeSnapshot = @js($runtimeSnapshot ?? null);

    function updateOnlineState(nextOnline, source = 'unknown') {
        if (typeof nextOnline !== 'boolean') {
            return;
        }

        state.online = nextOnline;
        emitMarker('tracker_runtime_online_state_updated', { online: nextOnline, source });
    }

    function userIsOnline() {
        if (typeof state.online === 'boolean') {
            return state.online;
        }

        if (typeof runtimeSnapshot?.online === 'boolean') {
            state.online = runtimeSnapshot.online;
            return state.online;
        }

        return false;
    }

    function handleGeoError(err, source = 'watch') {
        emitMarker('geolocation_denied_or_error', {
            source,
            code: Number(err?.code) || null,
            message: String(err?.message || ''),
        }, 'error');

        window.dispatchEvent(new CustomEvent('map:ui-error', {
            detail: [{
                type: 'error',
                message: 'Не вдалося отримати геолокацію курʼєра. Дозвольте доступ до геолокації в браузері, інакше фактична локація не буде підтверджена.',
            }],
        }));
    }

    function start(source = 'unknown') {
        emitMarker('tracker_boot_attempted', { source, online: userIsOnline(), hasWatch: state.watchId !== null });

        if (state.watchId !== null || !userIsCourier()) {
            return;
        }

        if (!userIsOnline()) {
            return;
        }

        if (!navigator.geolocation) {
            emitMarker('geolocation_not_supported', { source }, 'error');
            window.dispatchEvent(new CustomEvent('map:ui-error', {
                detail: [{
                    type: 'error',
                    message: 'Ваш браузер не підтримує геолокацію. Фактична локація курʼєра не підтверджена.',
                }],
            }));
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

                if (!state.firstPayloadReceived) {
                    state.firstPayloadReceived = true;
                    emitMarker('first_geolocation_payload_received', {
                        lat,
                        lng,
                        accuracy,
                        courierConfirmed,
                    });
                }

                if (!coordsValid) {
                    emitMarker('geolocation_payload_rejected', {
                        source: 'watch',
                        reason: 'invalid_coords',
                        lat,
                        lng,
                    }, 'warn');
                    return;
                }

                if (courierConfirmed && window.Livewire?.dispatch) {
                    window.Livewire.dispatch('courier-location', { lat, lng, accuracy });
                    emitMarker('courier_location_dispatched_to_livewire', { lat, lng, accuracy });
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
            (err) => handleGeoError(err, 'watch'),
            { enableHighAccuracy: true, maximumAge: 5000, timeout: 10000 }
        );

        emitMarker('geolocation_watch_started', { source });
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
        state.firstPayloadReceived = false;
    }

    function bootstrap(source = 'bootstrap') {
        state.bootstrapAttempts += 1;
        start(source);

        if (state.watchId !== null || !userIsCourier()) {
            return;
        }

        if (state.bootstrapAttempts >= 8) {
            return;
        }

        clearTimeout(state.bootstrapTimer);
        state.bootstrapTimer = setTimeout(() => bootstrap('bootstrap_retry'), 350);
    }

    if (!state.bootstrapped) {
        window.addEventListener('courier:online', () => {
            updateOnlineState(true, 'courier:online');
            start('courier:online');
        });

        window.addEventListener('courier:offline', () => {
            updateOnlineState(false, 'courier:offline');
            stop();
        });

        window.addEventListener('courier:runtime-sync', (event) => {
            const payload = Array.isArray(event?.detail) ? (event.detail[0] || {}) : (event?.detail || {});
            updateOnlineState(payload?.online ?? payload?.snapshot?.online ?? null, 'courier:runtime-sync');
            if (state.online === true) {
                start('courier:runtime-sync');
            } else if (state.online === false) {
                stop();
            }
        });

        window.addEventListener('courier:tracker-ready', () => bootstrap('courier:tracker-ready'));
        window.addEventListener('livewire:navigated', () => bootstrap('livewire:navigated'));
        state.bootstrapped = true;
    }

    bootstrap();
})();
</script>
