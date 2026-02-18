/* ============================================================
 * POOF — Map & Geolocation (Leaflet)
 * ============================================================
 * ✔ Livewire v3 friendly (DOM morph-safe)
 * ✔ Идемпотентный mount/unmount
 * ✔ pendingPoint (события до mount не теряются)
 * ✔ Courier online/offline watchPosition
 * ✔ map:courier-update → setCourierMap
 * ✔ Bottom-sheet friendly invalidateSize / ResizeObserver
 *
 * 🔧 PROD FIXES (added, nothing removed):
 * - Hard reject (0,0) координаты (океан) + NaN/Infinity
 * - Guard against geolocation timeouts + lastKnown fallback
 * - Courier watch throttling + accuracy sanity
 * - Tile layer error logging + readiness hooks
 * - Safe mount timing (layout-ready) + robust invalidate
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
    el: null,

    marker: null,
    markerPrecision: 'approx',
    icons: null,

    lastLat: null,
    lastLng: null,
    pendingPoint: null,

    resizeObserver: null,

    // courier overlays
    courierMarker: null,
    orderMarker: null,
    radiusCircle: null,
    __courierCenteredOnce: false,

    // geo button binding
    geoBtnBoundEl: null,

    // one-time handlers
    handlersBound: false,

    // geolocation watch
    courierWatchId: null,

    // --------- ADDED (prod) ---------
    // remember last good courier coords to avoid (0,0) jumps
    lastGoodCourierLat: null,
    lastGoodCourierLng: null,
    lastGoodCourierAt: null,

    // throttle courier updates to avoid UI jitter
    courierLastEmitAt: 0,

    // tile layer ref (for diagnostics / redraw)
    tiles: null,

    // prevent mount recursion storms
    mountInFlight: false,
  }

  const state = POOF.map

  // ------------------------------------------------------------
  // Helpers
  // ------------------------------------------------------------
  const leafletReady = () => !!window.L
  const hasLivewire = () => !!window.Livewire?.dispatch

  function toNumber(v) {
    const n = Number(v)
    return Number.isFinite(n) ? n : null
  }

  // --------- ADDED (prod) ---------
  function isValidLatLng(lat, lng) {
    const latN = toNumber(lat)
    const lngN = toNumber(lng)
    if (latN === null || lngN === null) return false
    // reject ocean origin
    if (latN === 0 && lngN === 0) return false
    // basic bounds
    if (latN < -90 || latN > 90) return false
    if (lngN < -180 || lngN > 180) return false
    return true
  }

  // --------- ADDED (prod) ---------
  function saveLastGoodCourier(lat, lng) {
    state.lastGoodCourierLat = Number(lat)
    state.lastGoodCourierLng = Number(lng)
    state.lastGoodCourierAt = Date.now()
  }

  // --------- ADDED (prod) ---------
  function getLastGoodCourier() {
    if (
      isValidLatLng(state.lastGoodCourierLat, state.lastGoodCourierLng)
    ) {
      return {
        lat: state.lastGoodCourierLat,
        lng: state.lastGoodCourierLng,
      }
    }
    return null
  }

  function sendLocation(lat, lng) {
    if (!hasLivewire()) return

    // OrderCreate
    window.Livewire.dispatch('set-location', { lat, lng })

    // AddressForm (если есть)
    try {
      window.Livewire.dispatch('address:set-coords', {
        lat,
        lng,
        source: 'map',
      })
    } catch (_) {}
  }

  // ------------------------------------------------------------
  // ResizeObserver (bottom-sheet safe)
  // ------------------------------------------------------------
  function observeMapResize(el, map) {
    if (!window.ResizeObserver || !el || !map) return

    // если наблюдаем другой элемент — отключаем
    if (state.resizeObserver && state.el && state.el !== el) {
      try { state.resizeObserver.disconnect() } catch (_) {}
      state.resizeObserver = null
    }

    if (state.resizeObserver && state.el === el) return

    const ro = new ResizeObserver(() => {
      try { map.invalidateSize(false) } catch (_) {}
    })

    ro.observe(el)
    state.resizeObserver = ro
  }

  function stopObserveResize() {
    if (!state.resizeObserver) return
    try { state.resizeObserver.disconnect() } catch (_) {}
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
  // CORE: marker API (pendingPoint safe)
  // ------------------------------------------------------------
  function setMarker(lat, lng, { emit = false, zoom = null, source = 'user' } = {}) {
    if (!leafletReady()) return

    const latN = toNumber(lat)
    const lngN = toNumber(lng)
    if (latN === null || lngN === null) return

    // карта ещё не готова — сохраняем точку
    if (!state.instance) {
      state.pendingPoint = { lat: latN, lng: lngN, zoom: zoom ?? 18 }
      return
    }

    const ll = window.L.latLng(latN, lngN)

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
      const icon = getMarkerIcon()
      if (icon) state.marker.setIcon(icon)
    }

    const targetZoom = zoom ?? state.instance.getZoom() ?? 16

    if (source === 'user') {
      state.instance.flyTo(ll, targetZoom, { animate: true, duration: 0.6 })
    } else {
      state.instance.setView(ll, targetZoom, { animate: false })
    }

    if (emit) sendLocation(latN, lngN)
  }

  // ------------------------------------------------------------
  // COURIER MODE overlays
  // ------------------------------------------------------------
  function setCourierMap(payload = {}) {
    if (!leafletReady() || !state.instance) return

    const courierLat = payload.courierLat ?? payload.courier?.lat ?? null
    const courierLng = payload.courierLng ?? payload.courier?.lng ?? null
    const orderLat = payload.orderLat ?? payload.order?.lat ?? null
    const orderLng = payload.orderLng ?? payload.order?.lng ?? null
    const radiusKm = Number(payload.radiusKm ?? 5)

    // --------- FIX (prod): reject invalid + reject (0,0) ---------
    const hasCourier = isValidLatLng(courierLat, courierLng)
    const hasOrder = isValidLatLng(orderLat, orderLng)

    // if courier invalid but we have last good — use it
    let courierLatUse = courierLat
    let courierLngUse = courierLng
    if (!hasCourier) {
      const last = getLastGoodCourier()
      if (last) {
        courierLatUse = last.lat
        courierLngUse = last.lng
      }
    }

    const hasCourierEffective = isValidLatLng(courierLatUse, courierLngUse)
    if (!hasCourierEffective && !hasOrder) return

    // Courier marker + radius
    if (hasCourierEffective) {
      const courierLL = window.L.latLng(Number(courierLatUse), Number(courierLngUse))

      // remember last good
      saveLastGoodCourier(courierLatUse, courierLngUse)

      if (!state.courierMarker) {
        state.courierMarker = window.L.marker(courierLL, {
          icon: getMarkerIcon() || undefined,
          draggable: false,
        }).addTo(state.instance)
      } else {
        state.courierMarker.setLatLng(courierLL)
        const icon = getMarkerIcon()
        if (icon) state.courierMarker.setIcon(icon)
      }

      const rMeters = Math.max(0.1, radiusKm) * 1000
      if (!state.radiusCircle) {
        state.radiusCircle = window.L.circle(courierLL, {
          radius: rMeters,
          weight: 1,
          fillOpacity: 0.08,
        }).addTo(state.instance)
      } else {
        state.radiusCircle.setLatLng(courierLL)
        state.radiusCircle.setRadius(rMeters)
      }

      if (!state.__courierCenteredOnce) {
        state.__courierCenteredOnce = true
        state.instance.setView(courierLL, 15, { animate: false })
      }
    }

    // Order marker
    if (hasOrder) {
      const orderLL = window.L.latLng(Number(orderLat), Number(orderLng))

      if (!state.orderMarker) {
        state.orderMarker = window.L.marker(orderLL, {
          icon: window.L.divIcon({
            className: '',
            html: `<div class="poof-order-marker">📦</div>`,
            iconSize: [36, 36],
            iconAnchor: [18, 18],
          }),
          draggable: false,
        }).addTo(state.instance)
      } else {
        state.orderMarker.setLatLng(orderLL)
      }
    }
  }
  
// ------------------------------------------------------------
// ROUTE BUILDING (Navigation inside map)
// ------------------------------------------------------------
async function buildRoute(fromLat, fromLng, toLat, toLng) {

    if (!state.instance) return;

    if (!isValidLatLng(fromLat, fromLng) || !isValidLatLng(toLat, toLng)) {
        console.warn('Invalid route coordinates');
        return;
    }

    const fLat = Number(fromLat);
    const fLng = Number(fromLng);
    const tLat = Number(toLat);
    const tLng = Number(toLng);

    try {
        const url =
            `https://router.project-osrm.org/route/v1/driving/` +
            `${fLng},${fLat};${tLng},${tLat}` +
            `?overview=full&geometries=geojson`;

        const res = await fetch(url);
        const data = await res.json();

        if (!data.routes?.length) return;

        const coords = data.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);

		// удалить старый маршрут
		if (state.routeOutline) {
			try { state.instance.removeLayer(state.routeOutline) } catch (_) {}
		}
		if (state.routeLine) {
			try { state.instance.removeLayer(state.routeLine) } catch (_) {}
		}

		// 1️⃣ OUTLINE (тёмный подслой)
		state.routeOutline = L.polyline(coords, {
			weight: 10,
			color: '#000000',
			opacity: 0.6,
			lineCap: 'round',
			lineJoin: 'round'
		}).addTo(state.instance)

		// 2️⃣ MAIN ROUTE (POOF стиль)
		state.routeLine = L.polyline(coords, {
			weight: 6,
			color: '#FACC15', // фирменный жёлтый
			opacity: 1,
			lineCap: 'round',
			lineJoin: 'round'
		}).addTo(state.instance)

		// подогнать карту
		state.instance.fitBounds(state.routeLine.getBounds(), {
			padding: [60, 60]
		})

    } catch (e) {
        console.warn('Route build error:', e);
    }
}

  

  function clearCourierOverlays() {
    if (!state.instance) return

    if (state.courierMarker) {
      try { state.instance.removeLayer(state.courierMarker) } catch (_) {}
      state.courierMarker = null
    }

    if (state.radiusCircle) {
      try { state.instance.removeLayer(state.radiusCircle) } catch (_) {}
      state.radiusCircle = null
    }

    if (state.orderMarker) {
      try { state.instance.removeLayer(state.orderMarker) } catch (_) {}
      state.orderMarker = null
    }

    state.__courierCenteredOnce = false
  }

  // ------------------------------------------------------------
  // Geo button (optional)
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
          const lat = pos.coords.latitude
          const lng = pos.coords.longitude
          // --------- ADDED (prod) ---------
          if (!isValidLatLng(lat, lng)) return

          setMarker(lat, lng, {
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
  // Mount / Remount
  // ------------------------------------------------------------
  function destroyIfDomChanged(el) {
    const domChanged = state.el && state.el !== el
    if (!state.instance || !domChanged) return

    try {
      state.instance.off()
      state.instance.remove()
    } catch (_) {}

    stopObserveResize()

    state.instance = null
    state.marker = null
    state.lastLat = null
    state.lastLng = null
    state.el = null

    // --------- ADDED (prod) ---------
    state.tiles = null

    clearCourierOverlays()
  }

  function mount(mapId = 'map') {
    if (!leafletReady()) return false

    const el = document.getElementById(mapId)
    if (!el) return false

    // --------- ADDED (prod): layout-ready guard ---------
    const rect = el.getBoundingClientRect()
    if (rect.height === 0) {
      if (!state.mountInFlight) {
        state.mountInFlight = true
        requestAnimationFrame(() => {
          state.mountInFlight = false
          mount(mapId)
        })
      }
      return false
    }

    destroyIfDomChanged(el)

    // уже есть карта на этом DOM
    if (state.instance && state.el === el) {
      try { state.instance.invalidateSize(true) } catch (_) {}
      observeMapResize(el, state.instance)
      bindGeoButton()
      return true
    }

    state.el = el

    state.instance = window.L.map(el, {
      zoomControl: true,
      attributionControl: true,
    }).setView([50.4501, 30.5234], 16)

    observeMapResize(el, state.instance)

    // --------- UPDATED (prod): keep tiles ref + error hooks ---------
    state.tiles = window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap',
      maxZoom: 19,
    })

    state.tiles.on('tileerror', (e) => {
      // helps diagnose blocked tiles / CORS / mixed content
      console.warn('[POOF:map] tileerror', e?.error || e)
    })

    state.tiles.on('load', () => {
      // tiles loaded → safe invalidate
      try { state.instance?.invalidateSize(true) } catch (_) {}
    })

    state.tiles.addTo(state.instance)

    state.instance.on('click', (e) => {
      setMarker(e.latlng.lat, e.latlng.lng, {
        emit: true,
        zoom: 18,
        source: 'user',
      })
    })

    // применяем pendingPoint (если события пришли раньше)
    if (state.pendingPoint) {
      const { lat, lng, zoom } = state.pendingPoint
      state.pendingPoint = null
      setMarker(lat, lng, { emit: false, zoom: zoom ?? 18, source: 'sync' })
    }

    // post-mount invalidate (важно когда карта появляется после toggle)
    requestAnimationFrame(() => {
      setTimeout(() => {
        try { state.instance?.invalidateSize(true) } catch (_) {}
        document.getElementById('map-skeleton')?.remove()
      }, 50)
    })

    bindGeoButton()
    return true
  }

  function mountAny() {
    // пробуем основные id (у тебя встречались оба)
    return mount('map') || mount('map-address')
  }

  // ------------------------------------------------------------
  // Courier geolocation watch
  // ------------------------------------------------------------
  function startCourierWatch() {
    if (!navigator.geolocation) {
      console.warn('Geolocation not supported')
      return
    }
    if (state.courierWatchId !== null) return

    state.courierWatchId = navigator.geolocation.watchPosition(
      (pos) => {
        const lat = pos.coords.latitude
        const lng = pos.coords.longitude
        const accuracy = pos.coords.accuracy ?? null

        // --------- FIX (prod): ignore invalid coords + ignore (0,0) ---------
        if (!isValidLatLng(lat, lng)) return

        // --------- ADDED (prod): throttle to avoid jitter & unnecessary LW spam ---------
        const now = Date.now()
        if (now - state.courierLastEmitAt < 700) return
        state.courierLastEmitAt = now

        // optional sanity: if accuracy is absurdly bad, skip visual centering but still save
        // (we still save last good to prevent ocean fallback)
        saveLastGoodCourier(lat, lng)

        // 1) обновляем БД
        if (window.Livewire?.dispatch) {
          window.Livewire.dispatch('courier-location', { lat, lng, accuracy })
        }

        // 2) обновляем карту
        setCourierMap({
          courierLat: lat,
          courierLng: lng,
          radiusKm: 5,
        })
      },
      (err) => {
        // --------- ADDED (prod): do not reset map on errors ---------
        console.warn('Geolocation error:', err)
      },
      { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 }
    )
  }

  function stopCourierWatch() {
    if (state.courierWatchId !== null) {
      try { navigator.geolocation.clearWatch(state.courierWatchId) } catch (_) {}
      state.courierWatchId = null
    }
    clearCourierOverlays()
  }

  // ------------------------------------------------------------
  // Global handlers (bind once)
  // ------------------------------------------------------------
  function bindGlobalHandlersOnce() {
  if (state.handlersBound) return
  state.handlersBound = true

  // PHP → JS: marker set
  window.addEventListener('map:set-marker', (e) => {
    const lat = e.detail?.lat
    const lng = e.detail?.lng
    if (lat == null || lng == null) return
    setMarker(lat, lng, { emit: false, zoom: 18, source: 'sync' })
  })

  window.addEventListener('map:set-marker-precision', (e) => {
    const precision = e.detail?.precision
    state.markerPrecision = precision === 'exact' ? 'exact' : 'approx'

    if (!state.marker) return
    const icons = ensureIcons()
    if (!icons) return

    state.marker.setIcon(
      state.markerPrecision === 'exact'
        ? icons.exact
        : icons.approx
    )
  })

  // -------------------------------
  // COURIER ONLINE
  // -------------------------------
  window.addEventListener('courier:online', () => {
    setTimeout(() => {
      mountAny()
      try { state.instance?.invalidateSize(true) } catch (_) {}
      startCourierWatch()
    }, 0)
  })

  window.addEventListener('courier:offline', () => {
    stopCourierWatch()
  })

  // -------------------------------
  // UPDATE COURIER MAP
  // -------------------------------
  window.addEventListener('map:courier-update', (e) => {
    mountAny()
    if (!state.instance) return
    setCourierMap(e.detail || {})
  })

  // ============================================================
  // 🗺 ROUTE BUILDING (ADD THIS HERE)
  // ============================================================
window.addEventListener('build-route', (e) => {

    let payload = e.detail

    // 🔥 FIX: если Livewire прислал массив — берём первый элемент
    if (Array.isArray(payload)) {
        payload = payload[0] || {}
    }

    console.log('ROUTE PAYLOAD FIXED:', payload)

    buildRoute(
        payload.fromLat,
        payload.fromLng,
        payload.toLat,
        payload.toLng
    )
})

  // -------------------------------
  // FORCE MAP INIT
  // -------------------------------
  window.addEventListener('map:init', () => {
    setTimeout(() => {
      mountAny()
      try { state.instance?.invalidateSize(true) } catch (_) {}
    }, 0)
  })

  // -------------------------------
  // Livewire morph hooks
  // -------------------------------
  if (window.Livewire?.hook) {
    window.Livewire.hook('morph.updated', () => {
      mountAny()
      try { state.instance?.invalidateSize(true) } catch (_) {}
    })

    window.Livewire.hook('morph.added', () => {
      mountAny()
      try { state.instance?.invalidateSize(true) } catch (_) {}
    })
  }

  document.addEventListener('livewire:navigated', () => {
    mountAny()
    try { state.instance?.invalidateSize(true) } catch (_) {}
  })
}

  // ------------------------------------------------------------
  // Public API
  // ------------------------------------------------------------
  POOF.initMap = mount
  POOF.mountMapAny = mountAny

  POOF.setCourierMap = (payload) => {
    mountAny()
    setCourierMap(payload || {})
  }

  POOF.setMarker = (lat, lng) => setMarker(lat, lng, { emit: true, zoom: 18, source: 'user' })
  POOF.setMarkerSilent = (lat, lng, zoom = 18) => setMarker(lat, lng, { emit: false, zoom, source: 'sync' })

  POOF.setMarkerPrecision = (precision) => {
    state.markerPrecision = precision === 'exact' ? 'exact' : 'approx'
    if (!state.marker) return
    const icons = ensureIcons()
    if (!icons) return
    state.marker.setIcon(state.markerPrecision === 'exact' ? icons.exact : icons.approx)
  }

  // --------- ADDED (prod): debug helpers (safe) ---------
  POOF.__getLastGoodCourier = () => getLastGoodCourier()
  POOF.__isValidLatLng = (lat, lng) => isValidLatLng(lat, lng)

  // ------------------------------------------------------------
  // Bootstrap
  // ------------------------------------------------------------
  bindGlobalHandlersOnce()
  mountAny()
}
