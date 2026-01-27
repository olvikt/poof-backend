/* ============================================================
 * POOF — Map & Geolocation (Leaflet)
 * ============================================================ */

export default function initMap() {
  window.POOF = window.POOF || {};
  const POOF = window.POOF;

  POOF.map = POOF.map || { instance: null, marker: null, el: null };

  function sendLocation(lat, lng) {
    if (!window.Livewire?.dispatch) return;
    Livewire.dispatch('set-location', { lat, lng });
  }

  function setMarker(lat, lng) {
    const state = POOF.map;
    if (!state.instance) return;

    if (!state.marker) {
      state.marker = L.marker([lat, lng], { draggable: true }).addTo(state.instance);

      state.marker.on('dragend', (e) => {
        const p = e.target.getLatLng();
        sendLocation(p.lat, p.lng);
      });
    } else {
      state.marker.setLatLng([lat, lng]);
    }

    state.instance.setView([lat, lng], 18);
    sendLocation(lat, lng);
  }

  function bindGeoButton() {
    const btn = document.getElementById('use-location-btn');
    if (!btn || btn.dataset.bound) return;

    btn.dataset.bound = '1';
    btn.addEventListener('click', () => {
      if (!navigator.geolocation) {
        alert('Геолокація не підтримується');
        return;
      }

      navigator.geolocation.getCurrentPosition(
        (pos) => setMarker(pos.coords.latitude, pos.coords.longitude),
        () => alert('Не вдалося отримати локацію'),
        { enableHighAccuracy: true }
      );
    });
  }

  function mount() {
    const el = document.getElementById('map');
    if (!el) return;

    const state = POOF.map;
    const changed = state.el && state.el !== el;

    if (state.instance && !changed) {
      // если DOM тот же — просто обновим размер
      state.instance.invalidateSize();
      bindGeoButton();
      return;
    }

    if (state.instance && changed) {
      try { state.instance.remove(); } catch (_) {}
      state.instance = null;
      state.marker = null;
    }

    state.el = el;

    // Leaflet должен быть загружен
    if (!window.L) return;

    state.instance = L.map(el).setView([50.4501, 30.5234], 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap',
    }).addTo(state.instance);

    // дать DOM отрисоваться
    setTimeout(() => {
      state.instance?.invalidateSize();
      document.getElementById('map-skeleton')?.remove();
    }, 300);

    state.instance.on('click', (e) => setMarker(e.latlng.lat, e.latlng.lng));

    bindGeoButton();
  }

  // public exports
  POOF.initMap = mount;
  POOF.setMarker = setMarker;

  // первый запуск + повтор при навигации livewire
  mount();
  document.addEventListener('livewire:navigated', mount);
}
