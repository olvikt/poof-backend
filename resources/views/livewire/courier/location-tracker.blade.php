<div></div>

<script>
(function () {
    let watchId = null

    function start() {
        if (watchId !== null) return;

        if (!navigator.geolocation) {
            console.warn('Geolocation not supported');
            return;
        }

        const userIsCourier = @json(auth()->user()?->isCourier() ?? false);

        if (!userIsCourier) return;

        watchId = navigator.geolocation.watchPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                const accuracy = pos.coords.accuracy ?? null;

                // 1) в Livewire (БД)
                if (window.Livewire?.dispatch) {
                    window.Livewire.dispatch('courier-location', { lat, lng, accuracy });
                }

                // 2) в карту (визуально)
                window.dispatchEvent(new CustomEvent('map:courier-update', {
                    detail: { courierLat: lat, courierLng: lng, radiusKm: 5 }
                }));
            },
            (err) => console.warn('Geolocation error', err),
            { enableHighAccuracy: true, maximumAge: 5000, timeout: 10000 }
        );
    }

    function stop() {
        if (watchId === null) return;
        try { navigator.geolocation.clearWatch(watchId); } catch (e) {}
        watchId = null;
    }

    window.addEventListener('courier:online', () => start());
window.addEventListener('courier:offline', () => stop());

// 🔥 автозапуск если уже online
document.addEventListener('livewire:navigated', () => {
    const isOnline = @json(auth()->user()?->isCourierOnline() ?? false);
    if (isOnline) {
        window.dispatchEvent(new Event('courier:online'));
    }
});

})();
</script>
