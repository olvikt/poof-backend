/* ============================================================
 * POOF — Map & Geolocation (Leaflet)
 * ============================================================
 *
 * ✔ Livewire v3
 * ✔ Идемпотентная инициализация
 * ✔ ЕДИНЫЙ КАНАЛ синхронизации: JS читает Livewire state
 * ✔ Address book ↔ map ↔ form
 */

export default function initMap() {
  // ------------------------------------------------------------
  // Global namespace
  // ------------------------------------------------------------
  window.POOF = window.POOF || {};
  const POOF = window.POOF;

  // ------------------------------------------------------------
  // Shared singleton state
  // ------------------------------------------------------------
  POOF.map = POOF.map || {
    instance: null,
    marker: null,
    el: null,

    handlersBound: false,
    geoBtnBoundEl: null,

    pendingPoint: null, // если coords пришли до init
    lastLat: null,
    lastLng: null,
  };

  const state = POOF.map;

  // ------------------------------------------------------------
  // Helpers
  // ------------------------------------------------------------
  function hasLivewire() {
    return !!window.Livewire?.dispatch;
  }

  function sendLocation(lat, lng) {
    if (!hasLivewire()) return;

    // OrderCreate
    Livewire.dispatch('set-location', { lat, lng });

    // AddressForm (если используется)
    try {
      Livewire.dispatch('address:set-coords', { lat, lng });
    } catch (_) {}
  }

  // ------------------------------------------------------------
  // CORE: единственный метод работы с маркером
  // ------------------------------------------------------------
  function setMarker(lat, lng, { emit = false, zoom = 18 } = {}) {
    if (!window.L) return;

    // если карта ещё не готова — откладываем
    if (!state.instance) {
      state.pendingPoint = { lat, lng };
      return;
    }

    // защита от лишних одинаковых обновлений
    if (state.lastLat === lat && state.lastLng === lng) return;
    state.lastLat = lat;
    state.lastLng = lng;

    const ll = L.latLng(lat, lng);

    if (!state.marker) {
      state.marker = L.marker(ll, { draggable: true }).addTo(state.instance);

      state.marker.on('dragend', (e) => {
        const p = e.target.getLatLng();
        setMarker(p.lat, p.lng, { emit: true, zoom: state.instance.getZoom() || zoom });
      });
    } else {
      state.marker.setLatLng(ll);
    }

    state.instance.setView(ll, zoom, { animate: false });

    if (emit) {
      sendLocation(lat, lng);
    }
  }

  // ------------------------------------------------------------
  // Geo button
  // ------------------------------------------------------------
  function bindGeoButton() {
    const btn = document.getElementById('use-location-btn');
    if (!btn || state.geoBtnBoundEl === btn) return;

    state.geoBtnBoundEl = btn;

    btn.addEventListener('click', () => {
      if (!navigator.geolocation) {
        alert('Геолокація не підтримується');
        return;
      }

      navigator.geolocation.getCurrentPosition(
        (pos) => {
          setMarker(
            pos.coords.latitude,
            pos.coords.longitude,
            { emit: true, zoom: 18 }
          );
        },
        () => alert('Не вдалося отримати локацію'),
        { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
      );
    });
  }

  // ------------------------------------------------------------
  // Global handlers (ONE TIME)
  // ------------------------------------------------------------
function bindGlobalHandlersOnce() {
  if (state.handlersBound) return;
  state.handlersBound = true;

  // ✅ PHP → JS (ЕДИНЫЙ КАНАЛ)
  window.addEventListener('map:set-marker', (e) => {
    const { lat, lng } = e.detail || {};
    if (lat == null || lng == null) return;

    setMarker(lat, lng, { emit: false, zoom: 18 });
  });

  // ре-маунт карты
  window.addEventListener('map:init', mount);
}

  // ------------------------------------------------------------
  // Mount map
  // ------------------------------------------------------------
  function mount() {
    const el = document.getElementById('map');
    if (!el || !window.L) return;

    const changed = state.el && state.el !== el;

    // карта уже есть и DOM тот же
    if (state.instance && !changed) {
      state.instance.invalidateSize();
      bindGeoButton();
      return;
    }

    // DOM сменился — убиваем карту
    if (state.instance && changed) {
      try {
        state.instance.off();
        state.instance.remove();
      } catch (_) {}
      state.instance = null;
      state.marker = null;
    }

    state.el = el;

    // создаём карту
    state.instance = L.map(el, {
      zoomControl: true,
      attributionControl: true,
    }).setView([50.4501, 30.5234], 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap',
    }).addTo(state.instance);

    // клик по карте
    state.instance.on('click', (e) => {
      setMarker(e.latlng.lat, e.latlng.lng, { emit: true, zoom: 18 });
    });

    // применяем отложенную точку
    if (state.pendingPoint) {
      const { lat, lng } = state.pendingPoint;
      state.pendingPoint = null;
      setMarker(lat, lng, { emit: false, zoom: 18 });
    }

    setTimeout(() => {
      try {
        state.instance.invalidateSize();
      } catch (_) {}
      document.getElementById('map-skeleton')?.remove();
    }, 250);

    bindGeoButton();
  }

  // ------------------------------------------------------------
  // Public API
  // ------------------------------------------------------------
  POOF.initMap = mount;

  // ❗Оставляем старое поведение (emit:true) чтобы ничего не сломать
  POOF.setMarker = (lat, lng) => setMarker(lat, lng, { emit: true, zoom: 18 });

  // ✅ Новый метод: поставить маркер ТИХО (без отправки обратно в Livewire)
  POOF.setMarkerSilent = (lat, lng, zoom = 18) =>
    setMarker(lat, lng, { emit: false, zoom });

  // ------------------------------------------------------------
  // Bootstrap
  // ------------------------------------------------------------
  bindGlobalHandlersOnce();
  mount();

  document.addEventListener('livewire:navigated', mount);
}
