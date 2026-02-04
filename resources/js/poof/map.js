/* ============================================================
 * POOF — Map & Geolocation (Leaflet)
 * ============================================================
 *
 * ✔ Livewire v3
 * ✔ Идемпотентная инициализация
 * ✔ ЕДИНЫЙ КАНАЛ: PHP → JS → Map
 * ✔ Поддержка нескольких контейнеров (mapId)
 * ✔ Bottom-sheet friendly (ResizeObserver + invalidateSize)
 * ✔ Брендированный маркер POOF (SVG/IMG)
 */

export default function initMap() {
  // ------------------------------------------------------------
  // Namespace
  // ------------------------------------------------------------
  window.POOF = window.POOF || {}
  const POOF = window.POOF

  // ------------------------------------------------------------
  // Shared singleton state
  // ------------------------------------------------------------
  POOF.map = POOF.map || {
    instance: null,
    marker: null,
    el: null,

    handlersBound: false,
    geoBtnBoundEl: null,

    pendingPoint: null,
    lastLat: null,
    lastLng: null,

    // marker icon cache (создаём только когда Leaflet доступен)
    icons: null,

    // last precision for restoring after remount
    markerPrecision: 'approx',

    // map container observer
    resizeObserver: null,
  }

  const state = POOF.map

  // ------------------------------------------------------------
  // Helpers
  // ------------------------------------------------------------
  function leafletReady() {
    return !!window.L
  }

  function hasLivewire() {
    return !!window.Livewire?.dispatch
  }

  // Единственный канал Map -> Livewire
  function sendLocation(lat, lng) {
    if (!hasLivewire()) return

    // OrderCreate
    window.Livewire.dispatch('set-location', { lat, lng })

    // AddressForm
    try {
      window.Livewire.dispatch('address:set-coords', {
        lat,
        lng,
        source: 'map',
      })
    } catch (_) {}
  }

  function toNumber(v) {
    const n = Number(v)
    return Number.isFinite(n) ? n : null
  }

  // ------------------------------------------------------------
  // ResizeObserver — критично для bottom-sheet + Leaflet
  // ------------------------------------------------------------
  function observeMapResize(el, map) {
    if (!window.ResizeObserver || !el || !map) return

    // если уже наблюдаем другой элемент — отключаем
    if (state.resizeObserver && state.el && state.el !== el) {
      try {
        state.resizeObserver.disconnect()
      } catch (_) {}
      state.resizeObserver = null
    }

    // если уже наблюдаем этот же элемент — не дублируем
    if (state.resizeObserver && state.el === el) return

    const ro = new ResizeObserver(() => {
      try {
        map.invalidateSize(false)
      } catch (_) {}
    })

    ro.observe(el)
    state.resizeObserver = ro
  }

  function stopObserveResize() {
    if (!state.resizeObserver) return
    try {
      state.resizeObserver.disconnect()
    } catch (_) {}
    state.resizeObserver = null
  }

  // ------------------------------------------------------------
  // Marker icons (lazy)
  // ------------------------------------------------------------
  function ensureIcons() {
    if (state.icons) return state.icons
    if (!leafletReady()) return null

    const LOGO_SRC = '/images/logo-poof.svg'

    const createPoofMarkerIcon = (precision = 'approx') =>
      window.L.divIcon({
        className: '',
        html: `
          <div class="poof-marker ${precision === 'exact' ? 'exact' : ''}">
            <img src="${LOGO_SRC}" alt="POOF" />
          </div>
        `,
        iconSize: [42, 42],
        iconAnchor: [21, 21],
      })

    state.icons = {
      approx: createPoofMarkerIcon('approx'),
      exact: createPoofMarkerIcon('exact'),
    }

    return state.icons
  }

  function getMarkerIcon() {
    const icons = ensureIcons()
    if (!icons) return null
    return state.markerPrecision === 'exact' ? icons.exact : icons.approx
  }

  // ------------------------------------------------------------
  // CORE: единая точка работы с маркером
  // ------------------------------------------------------------
function setMarker(lat, lng, { emit = false, zoom = null, source = 'user' } = {}) {
  if (!leafletReady()) return

  const latN = Number(lat)
  const lngN = Number(lng)
  if (!Number.isFinite(latN) || !Number.isFinite(lngN)) return

  // карта ещё не готова
  if (!state.instance) {
    state.pendingPoint = { lat: latN, lng: lngN }
    return
  }

  const ll = window.L.latLng(latN, lngN)

  const samePoint = state.lastLat === latN && state.lastLng === lngN
  state.lastLat = latN
  state.lastLng = lngN

  // marker
  if (!state.marker) {
    const icon = getMarkerIcon()

    state.marker = window.L.marker(ll, {
      draggable: true,
      icon: icon || undefined,
    }).addTo(state.instance)

    state.marker.on('dragend', (e) => {
      const p = e.target.getLatLng()
      setMarker(p.lat, p.lng, {
        emit: true,
        zoom: state.instance?.getZoom() || 18,
        source: 'user',
      })
    })
  } else {
    state.marker.setLatLng(ll)
  }

  // ✅ КАМЕРА: используем flyTo (самый стабильный фокус)
  const targetZoom = zoom ?? state.instance.getZoom() ?? 16

  if (source === 'user') {
    // Даже если точка та же — всё равно фокусируем
    state.instance.flyTo(ll, targetZoom, { animate: true, duration: 0.6 })
  } else {
    // sync из PHP — без анимаций
    state.instance.setView(ll, targetZoom, { animate: false })
  }

  if (emit) sendLocation(latN, lngN)
}

  // ------------------------------------------------------------
  // Geo button
  // ------------------------------------------------------------
  function bindGeoButton() {
    const btn = document.getElementById('use-location-btn')
    if (!btn || state.geoBtnBoundEl === btn) return

    state.geoBtnBoundEl = btn

    btn.addEventListener('click', () => {
      if (!navigator.geolocation) {
        alert('Геолокація не підтримується')
        return
      }

      navigator.geolocation.getCurrentPosition(
        (pos) => {
          setMarker(pos.coords.latitude, pos.coords.longitude, {
            emit: true,
            zoom: 18,
            source: 'user',
          })
        },
        () => alert('Не вдалося отримати локацію'),
        { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
      )
    })
  }

  // ------------------------------------------------------------
  // Global one-time handlers
  // ------------------------------------------------------------
  function bindGlobalHandlersOnce() {
    if (state.handlersBound) return
    state.handlersBound = true

    // PHP → JS: синхронизация координат
    window.addEventListener('map:set-marker', (e) => {
      const { lat, lng } = e.detail || {}
      if (lat == null || lng == null) return

      setMarker(lat, lng, { emit: false, source: 'sync' })
    })

    // PHP → JS: смена точности маркера (approx / exact)
    window.addEventListener('map:set-marker-precision', (e) => {
      const precision = e.detail?.precision
      if (!precision) return

      state.markerPrecision = precision === 'exact' ? 'exact' : 'approx'

      if (state.marker) {
        const icons = ensureIcons()
        if (icons) {
          state.marker.setIcon(state.markerPrecision === 'exact' ? icons.exact : icons.approx)
        }
      }
    })

    // UI → JS: sheet реально открылся
    window.addEventListener('poof:sheet-opened', (e) => {
      if (e.detail?.name !== 'editAddress') return

      const map = state.instance
      if (!map) return

      // 2 invalidateSize — best practice
      requestAnimationFrame(() => {
        try {
          map.invalidateSize(false)
        } catch (_) {}

        setTimeout(() => {
          try {
            map.invalidateSize(true)
            map.panTo(map.getCenter(), { animate: false })
          } catch (_) {}
        }, 100)
      })
    })
  }

  // ------------------------------------------------------------
  // Mount / Remount
  // ------------------------------------------------------------
  function mount(mapId = 'map-address') {
    const el = document.getElementById(mapId)
    if (!el) return

    // Leaflet ещё не подгрузился — выходим
    if (!leafletReady()) return

    const domChanged = state.el && state.el !== el

    // карта уже есть и DOM тот же
    if (state.instance && !domChanged) {
      try {
        state.instance.invalidateSize()
      } catch (_) {}

      // на всякий — следим за resize
      observeMapResize(el, state.instance)
      bindGeoButton()
      return
    }

    // DOM сменился — уничтожаем карту
    if (state.instance && domChanged) {
      try {
        state.instance.off()
        state.instance.remove()
      } catch (_) {}

      stopObserveResize()

      state.instance = null
      state.marker = null
      state.lastLat = null
      state.lastLng = null
    }

    state.el = el

    // создаём карту
    state.instance = window.L.map(el, {
      zoomControl: true,
      attributionControl: true,
    }).setView([50.4501, 30.5234], 16)

    // наблюдаем размеры (bottom-sheet safe)
    observeMapResize(el, state.instance)

    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap',
    }).addTo(state.instance)

    // клик по карте
    state.instance.on('click', (e) => {
      setMarker(e.latlng.lat, e.latlng.lng, {
        emit: true,
        zoom: 18,
        source: 'user',
      })
    })

    // применяем отложенную точку
    if (state.pendingPoint) {
      const { lat, lng } = state.pendingPoint
      state.pendingPoint = null
      setMarker(lat, lng, { emit: false, zoom: 18, source: 'sync' })
    }

    // post-mount fix
    setTimeout(() => {
      try {
        state.instance?.invalidateSize()
      } catch (_) {}
      document.getElementById('map-skeleton')?.remove()
    }, 250)

    bindGeoButton()
  }

  // ------------------------------------------------------------
  // Public API
  // ------------------------------------------------------------
  POOF.initMap = mount

  // старое поведение (emit:true)
  POOF.setMarker = (lat, lng) => setMarker(lat, lng, { emit: true, zoom: 18, source: 'user' })

  // тихо
  POOF.setMarkerSilent = (lat, lng, zoom = 18) => setMarker(lat, lng, { emit: false, zoom, source: 'sync' })

  // точность
  POOF.setMarkerPrecision = (precision) => {
    state.markerPrecision = precision === 'exact' ? 'exact' : 'approx'
    if (!state.marker) return
    const icons = ensureIcons()
    if (!icons) return
    state.marker.setIcon(state.markerPrecision === 'exact' ? icons.exact : icons.approx)
  }

  // ------------------------------------------------------------
  // Bootstrap
  // ------------------------------------------------------------
  bindGlobalHandlersOnce()

  // Пытаемся смонтировать оба типа контейнеров (если есть)
  mount('map')
  mount('map-address')

  document.addEventListener('livewire:navigated', () => {
    mount('map')
    mount('map-address')
  })
}
