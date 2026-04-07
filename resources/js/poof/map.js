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
import { buildDeniedGeolocationUiState, shouldShowDefaultCityUnconfirmedState } from './geolocation-hotfix.js'

if (typeof window !== 'undefined' && window.__poofUseCurrentLocationPendingBound !== true) {
  window.__poofUseCurrentLocationPendingBound = true
  window.__poofUseCurrentLocationPending = false

  window.addEventListener('use-current-location', () => {
    if (!window.POOF?.map?.handlersBound) {
      window.__poofUseCurrentLocationPending = true
    }
  })
}

function isFiniteLatLngForBootstrap(lat, lng) {
  const latN = Number(lat)
  const lngN = Number(lng)

  if (!Number.isFinite(latN) || !Number.isFinite(lngN)) return false
  if (latN === 0 && lngN === 0) return false
  if (latN < -90 || latN > 90) return false
  if (lngN < -180 || lngN > 180) return false

  return true
}

export function resolveMapBootstrapRejectionReason(bootstrap = null) {
  if (!bootstrap || typeof bootstrap !== 'object') return 'invalid_payload'
  if (
    isFiniteLatLngForBootstrap(bootstrap?.orderLat, bootstrap?.orderLng)
    || isFiniteLatLngForBootstrap(bootstrap?.courierLat, bootstrap?.courierLng)
  ) {
    return null
  }

  return 'invalid_or_stale_coords'
}

function shouldApplyPersistedLocationOnBootstrap({
  persistedLocation = null,
  bootstrapApplied = false,
  hasActiveOrderBootstrap = false,
  isAddressPickerFlow = false,
} = {}) {
  return Boolean(
    persistedLocation
    && !bootstrapApplied
    && !hasActiveOrderBootstrap
    && !isAddressPickerFlow,
  )
}

function buildCurrentLocationFallbackPlan({
  allowPersistedFallback = false,
  persistedLocation = null,
  closeAddressBook = false,
  source = 'user-fallback',
  zoom = 17,
} = {}) {
  if (!allowPersistedFallback || !persistedLocation || !isFiniteLatLngForBootstrap(persistedLocation.lat, persistedLocation.lng)) {
    return null
  }

  return {
    lat: Number(Number(persistedLocation.lat).toFixed(6)),
    lng: Number(Number(persistedLocation.lng).toFixed(6)),
    source,
    persistSource: persistedLocation.source || 'persisted',
    zoom,
    closeAddressBook: false,
    message: 'Точну геолокацію не вдалося отримати, тому показали останню збережену точку. За потреби посуньте мапу вручну.',
    warning: 'Точну локацію не вдалося отримати — використали останню збережену точку.',
    requestedCloseAddressBook: Boolean(closeAddressBook),
    usedPersistedFallback: true,
  }
}

function shouldIgnoreStaleAddressPickerSyncPoint({
  isAddressPickerFlow = false,
  lat = null,
  lng = null,
  source = 'sync',
  authoritativePoint = null,
  preferredPoint = null,
  now = Date.now(),
} = {}) {
  if (!isAddressPickerFlow || source !== 'sync') return false

  const hasFreshPoint = (point) => (
    point
    && isFiniteLatLngForBootstrap(point.lat, point.lng)
    && now - Number(point.updatedAt || 0) <= 5000
  )

  if (hasFreshPoint(authoritativePoint)) {
    return !(
      Math.abs(Number(authoritativePoint.lat) - Number(lat)) <= 0.000001
      && Math.abs(Number(authoritativePoint.lng) - Number(lng)) <= 0.000001
    )
  }

  if (hasFreshPoint(preferredPoint)) {
    return !(
      Math.abs(Number(preferredPoint.lat) - Number(lat)) <= 0.000001
      && Math.abs(Number(preferredPoint.lng) - Number(lng)) <= 0.000001
    )
  }

  return false
}

function normalizeRuntimeObservabilityReason(reason, fallback = 'unspecified') {
  if (typeof reason !== 'string') return fallback
  const normalized = reason.trim()
  return normalized !== '' ? normalized : fallback
}


function normalizeCourierRuntimePayloadContract(payload = {}) {
  const snapshot = payload?.snapshot && typeof payload.snapshot === 'object'
    ? payload.snapshot
    : payload

  const online = typeof snapshot?.online === 'boolean'
    ? snapshot.online
    : null

  const status = typeof snapshot?.status === 'string' && snapshot.status.trim() !== ''
    ? snapshot.status.trim()
    : null

  if (online === null && status === null) return null

  return {
    version: Number(snapshot?.version || payload?.version) || 1,
    online,
    status,
    reason: typeof snapshot?.reason === 'string' && snapshot.reason.trim() !== ''
      ? snapshot.reason.trim()
      : (typeof payload?.reason === 'string' && payload.reason.trim() !== ''
        ? payload.reason.trim()
        : null),
    changed: typeof snapshot?.changed === 'boolean'
      ? snapshot.changed
      : (typeof payload?.changed === 'boolean' ? payload.changed : null),
    source: typeof snapshot?.source === 'string' && snapshot.source.trim() !== ''
      ? snapshot.source.trim()
      : (typeof payload?.source === 'string' && payload.source.trim() !== '' ? payload.source.trim() : 'runtime_hint'),
    busy: typeof snapshot?.busy === 'boolean'
      ? snapshot.busy
      : null,
    activeOrderStatus: typeof snapshot?.active_order_status === 'string'
      ? snapshot.active_order_status
      : null,
    updatedAt: Number(snapshot?.updatedAt || payload?.updatedAt) || Date.now(),
  }
}

function buildCourierRuntimeSyncEnvelope(rawPayload = {}, options = {}) {
  const payload = normalizeCourierRuntimePayloadContract(rawPayload)
  if (!payload) return null

  return {
    type: 'courier-runtime-sync',
    version: Number(options.version || payload.version) || 1,
    tabId: options.tabId || null,
    emittedAt: Number(options.emittedAt) || Date.now(),
    payload: {
      ...payload,
      version: Number(options.version || payload.version) || 1,
      reason: payload.reason ?? options.reason ?? null,
      source: options.source || payload.source || 'runtime_hint',
      changed: typeof payload.changed === 'boolean' ? payload.changed : false,
    },
  }
}

function normalizeIncomingCourierRuntimeSyncMessage(message = null, currentTabId = null) {
  if (!message || message.type !== 'courier-runtime-sync') {
    return { action: 'ignore', reason: 'wrong_message_type' }
  }

  if (message.tabId && currentTabId && message.tabId === currentTabId) {
    return { action: 'ignore', reason: 'same_tab' }
  }

  const payload = normalizeCourierRuntimePayloadContract(message.payload || {})

  if (!payload) {
    return { action: 'reread', reason: 'invalid_payload' }
  }

  return {
    action: 'apply',
    reason: payload.reason ?? 'cross_tab_runtime_sync',
    payload: {
      ...payload,
      version: Number(message.version || payload.version) || 1,
      changed: typeof payload.changed === 'boolean' ? payload.changed : false,
      source: payload.source || 'cross_tab_runtime_sync',
    },
  }
}


function buildCourierRuntimeEvidenceView({
  counters = {},
  signals = [],
  authSessionLost = false,
  crossTab = {},
  geoLeadership = {},
  serverRuntime = null,
  serverRuntimeError = null,
  generatedAt = Date.now(),
} = {}) {
  const normalizedSignals = Array.isArray(signals)
    ? signals
      .filter((signal) => signal && typeof signal === 'object')
      .map((signal) => ({
        event: normalizeRuntimeObservabilityReason(signal.event, 'runtime_event'),
        reason: normalizeRuntimeObservabilityReason(signal.reason),
        level: signal.level === 'warn' || signal.level === 'error' ? signal.level : 'info',
        ts: Number(signal.ts) || 0,
        meta: signal.meta && typeof signal.meta === 'object' ? signal.meta : {},
      }))
      .sort((left, right) => Number(right.ts || 0) - Number(left.ts || 0))
    : []

  const topCounters = Object
    .entries(counters && typeof counters === 'object' ? counters : {})
    .filter(([key, value]) => typeof key === 'string' && key !== '' && Number.isFinite(Number(value)))
    .map(([key, value]) => ({ key, count: Number(value) }))
    .sort((left, right) => right.count - left.count)
    .slice(0, 8)

  return {
    generatedAt: Number(generatedAt) || Date.now(),
    authSessionLost: Boolean(authSessionLost),
    counters: counters && typeof counters === 'object' ? { ...counters } : {},
    topCounters,
    recentSignals: normalizedSignals.slice(0, 12),
    crossTab: {
      tabId: typeof crossTab.tabId === 'string' ? crossTab.tabId : null,
      lastSignature: typeof crossTab.lastSignature === 'string' ? crossTab.lastSignature : null,
      lastEmittedAt: Number(crossTab.lastEmittedAt) || 0,
      channelEnabled: Boolean(crossTab.channelEnabled),
    },
    geoLeadership: {
      desired: Boolean(geoLeadership.desired),
      active: Boolean(geoLeadership.active),
      mode: typeof geoLeadership.mode === 'string' ? geoLeadership.mode : null,
    },
    serverRuntime,
    serverRuntimeError: typeof serverRuntimeError === 'string' && serverRuntimeError.trim() !== ''
      ? serverRuntimeError.trim()
      : null,
  }
}

function shouldEnableRuntimeDiagnostics() {
  return String(import.meta?.env?.VITE_MAP_RUNTIME_DIAGNOSTICS || '').toLowerCase() === 'true'
}

function resolveCourierMarkerLifecycle({
  isAddressPickerFlow = false,
  hasCourier = false,
  hasOrder = false,
} = {}) {
  const hasCourierMarker = Boolean(hasCourier)
  const hasOrderMarker = Boolean(hasOrder)
  const hasCourierVisuals = hasCourierMarker || hasOrderMarker

  return {
    shouldRenderCourierMarker: hasCourierMarker,
    shouldRenderOrderMarker: hasOrderMarker,
    shouldRenderRadiusCircle: hasCourierMarker,
    shouldClearFloatingMarker: !isAddressPickerFlow && hasCourierVisuals,
  }
}

export default function initMap() {
  // ------------------------------------------------------------
  // Namespace
  // ------------------------------------------------------------
  window.POOF = window.POOF || {}
  const POOF = window.POOF
  // Optional deep diagnostics: disabled by default in production.
  const DEBUG_MAP = String(import.meta?.env?.VITE_MAP_DEBUG || '').toLowerCase() === 'true'
  const RUNTIME_DIAGNOSTICS = shouldEnableRuntimeDiagnostics()
  const API_BASE = (import.meta?.env?.VITE_API_URL || '').replace(/\/$/, '')
  const LAST_KNOWN_USER_LOCATION_KEY = 'poof:last-known-user-location:v1'
  const COURIER_RUNTIME_SYNC_CHANNEL = 'poof:courier-runtime-sync:v1'
  const COURIER_RUNTIME_SYNC_STORAGE_KEY = 'poof:courier-runtime-sync:message:v1'
  const COURIER_GEO_WATCH_LEASE_KEY = 'poof:courier-geo-watch:leader-lease:v1'
  const COURIER_GEO_WATCH_LEASE_TTL_MS = 12000
  const COURIER_GEO_WATCH_HEARTBEAT_MS = 4000
  const COURIER_GEO_WATCH_RETRY_MS = 3500

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

    courierLastAccuracy: null,
    courierConfirmed: false,

    // --------- ADDED (prod) ---------
    // remember last good courier coords to avoid (0,0) jumps
    lastGoodCourierLat: null,
    lastGoodCourierLng: null,
    lastGoodCourierAt: null,

    // throttle courier updates to avoid UI jitter
    courierLastEmitAt: 0,

    // tile layer ref (for diagnostics / redraw)
    tiles: null,

    routeLine: null,
    routeOutline: null,

    // prevent mount recursion storms
    mountInFlight: false,

    // reverse geocode stability guards
    reverseDebounceTimer: null,
    reverseRequestId: 0,
    addressLocked: false,
    isReverseUpdating: false,

    // debug checkpoints for mobile active-order flow
    debugBootstrapLogged: false,
    debugFirstCourierCoordsLogged: false,
    debugFirstOrderCoordsLogged: false,

    hasActiveOrderBootstrap: false,
    isResolvingUserLocation: false,
    hasResolvedUserLocation: false,
    isAddressPickerFlow: false,
    geoActionInFlight: false,
    preferredVisibleAddressPoint: null,
    authoritativeAddressPickerPoint: null,

    crossTabRuntimeBound: false,
    crossTabRuntimeChannel: null,
    crossTabRuntimeTabId: null,
    crossTabRuntimeLastSignature: null,
    crossTabRuntimeLastEmittedAt: 0,

    courierGeoLeaderDesired: false,
    courierGeoLeaderActive: false,
    courierGeoLeaderMode: null,
    courierGeoLeaseHeartbeatTimer: null,
    courierGeoAcquireRetryTimer: null,
    courierGeoLockAbortController: null,
    courierGeoStorageBound: false,
    runtimeSignalCounters: {},
    runtimeSignalHistory: [],
    authSessionLost: false,
    runtimeEvidenceRequestBound: false,
    defaultCityFallbackLogged: false,
  }

  const state = POOF.map

  // ------------------------------------------------------------
  // Helpers
  // ------------------------------------------------------------
  const leafletReady = () => !!window.L
  function hasLivewire() {
    return typeof window.Livewire !== 'undefined'
  }

  function isSavedAddressLocked() {
    return Boolean(window.POOF?.addressState?.locked)
  }

  function toNumber(v) {
    const n = Number(v)
    return Number.isFinite(n) ? n : null
  }

  function canUseStorage() {
    try {
      return typeof window !== 'undefined' && typeof window.localStorage !== 'undefined'
    } catch (_) {
      return false
    }
  }

  function canUseBroadcastChannel() {
    try {
      return typeof window !== 'undefined'
        && typeof window.BroadcastChannel !== 'undefined'
    } catch (_) {
      return false
    }
  }

  function isRuntimeBlockedByAuthLoss() {
    return state.authSessionLost === true
  }

  function ensureCrossTabRuntimeTabId() {
    if (state.crossTabRuntimeTabId) return state.crossTabRuntimeTabId

    const generated = `tab-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`

    if (canUseStorage()) {
      try {
        const existing = window.sessionStorage?.getItem('poof:courier-runtime-tab-id:v1')
        if (existing) {
          state.crossTabRuntimeTabId = existing
          return existing
        }

        window.sessionStorage?.setItem('poof:courier-runtime-tab-id:v1', generated)
      } catch (_) {}
    }

    state.crossTabRuntimeTabId = generated

    return generated
  }

  function emitRuntimeSignal(event, reason, options = {}) {
    const normalizedReason = normalizeRuntimeObservabilityReason(reason)
    const level = options.level === 'error' || options.level === 'warn' ? options.level : 'info'
    const detail = {
      event: normalizeRuntimeObservabilityReason(event, 'runtime_event'),
      reason: normalizedReason,
      level,
      ts: Date.now(),
      meta: options.meta && typeof options.meta === 'object' ? options.meta : {},
    }

    const counterKey = `${detail.event}:${detail.reason}`
    state.runtimeSignalCounters[counterKey] = Number(state.runtimeSignalCounters[counterKey] || 0) + 1
    state.runtimeSignalHistory.unshift(detail)
    if (state.runtimeSignalHistory.length > 40) {
      state.runtimeSignalHistory.length = 40
    }

    window.dispatchEvent(new CustomEvent('poof:courier-runtime-observe', { detail }))

    if (!RUNTIME_DIAGNOSTICS) return

    const label = `[POOF:runtime][${detail.level}] ${detail.event}`
    if (detail.level === 'error') {
      console.error(label, detail)
      return
    }
    if (detail.level === 'warn') {
      console.warn(label, detail)
      return
    }
    console.info(label, detail)
  }

  function normalizeCourierRuntimePayload(payload = {}) {
    return normalizeCourierRuntimePayloadContract(payload)
  }


  function emitCrossTabCourierRuntimeSync(rawPayload = {}, options = {}) {
    const payload = normalizeCourierRuntimePayload(rawPayload)
    if (!payload) return
    const signature = `${payload.online === null ? 'null' : String(payload.online)}|${payload.status || 'null'}|${payload.reason || options.reason || 'null'}`
    const now = Date.now()

    if (
      state.crossTabRuntimeLastSignature === signature
      && now - Number(state.crossTabRuntimeLastEmittedAt || 0) < 1200
    ) {
      return
    }

    state.crossTabRuntimeLastSignature = signature
    state.crossTabRuntimeLastEmittedAt = now

    const envelope = buildCourierRuntimeSyncEnvelope(payload, {
      tabId: ensureCrossTabRuntimeTabId(),
      emittedAt: now,
      reason: options.reason,
      source: options.source || 'cross_tab_runtime_sync',
    })
    if (!envelope) return

    emitRuntimeSignal('cross_tab_runtime_sync_emit', envelope.payload.reason ?? options.reason ?? 'runtime_sync_emit', {
      meta: {
        transport: state.crossTabRuntimeChannel ? 'broadcast_channel+storage' : 'storage',
        online: payload.online,
        status: payload.status,
      },
    })

    if (state.crossTabRuntimeChannel) {
      try {
        state.crossTabRuntimeChannel.postMessage(envelope)
      } catch (_) {}
    }

    if (canUseStorage()) {
      try {
        window.localStorage.setItem(COURIER_RUNTIME_SYNC_STORAGE_KEY, JSON.stringify(envelope))
        window.localStorage.removeItem(COURIER_RUNTIME_SYNC_STORAGE_KEY)
      } catch (_) {}
    }
  }

  function handleIncomingCrossTabRuntimeSync(message = null) {
    const normalized = normalizeIncomingCourierRuntimeSyncMessage(message, ensureCrossTabRuntimeTabId())

    if (normalized.action === 'ignore') return

    if (normalized.action === 'reread') {
      emitRuntimeSignal('cross_tab_runtime_sync_ignored', normalized.reason || 'invalid_payload', {
        level: 'warn',
      })

      if (window.Livewire?.dispatch) {
        window.Livewire.dispatch('courier-online-toggled', {
          changed: false,
          reason: 'cross_tab_runtime_sync_malformed',
          source: 'cross_tab_runtime_sync',
        })
      }

      return
    }

    const payload = normalized.payload || {}

    emitRuntimeSignal('cross_tab_runtime_sync_repair_applied', normalized.reason || 'cross_tab_runtime_sync', {
      meta: {
        online: payload.online,
        status: payload.status,
        version: payload.version,
        source: payload.source,
      },
    })

    window.dispatchEvent(new CustomEvent('courier:runtime-sync', {
      detail: {
        ...payload,
        __crossTab: true,
      },
    }))

    if (window.Livewire?.dispatch) {
      window.Livewire.dispatch('courier-online-toggled', {
        changed: false,
        reason: normalized.reason || 'cross_tab_runtime_sync',
        source: payload.source || 'cross_tab_runtime_sync',
      })
    }
  }

  function bindCrossTabRuntimeSyncOnce() {
    if (state.crossTabRuntimeBound) return
    state.crossTabRuntimeBound = true
    ensureCrossTabRuntimeTabId()

    if (canUseBroadcastChannel()) {
      try {
        state.crossTabRuntimeChannel = new BroadcastChannel(COURIER_RUNTIME_SYNC_CHANNEL)
        state.crossTabRuntimeChannel.onmessage = (event) => {
          handleIncomingCrossTabRuntimeSync(event?.data || null)
        }
      } catch (_) {
        state.crossTabRuntimeChannel = null
      }
    }

    window.addEventListener('storage', (event) => {
      if (event.key !== COURIER_RUNTIME_SYNC_STORAGE_KEY || !event.newValue) return

      try {
        const message = JSON.parse(event.newValue)
        handleIncomingCrossTabRuntimeSync(message)
      } catch (_) {}
    })
  }

  function canUseWebLocks() {
    try {
      return typeof navigator !== 'undefined'
        && typeof navigator.locks !== 'undefined'
        && typeof navigator.locks.request === 'function'
        && typeof window.AbortController !== 'undefined'
    } catch (_) {
      return false
    }
  }

  function clearCourierGeoAcquireRetry() {
    if (state.courierGeoAcquireRetryTimer) {
      clearTimeout(state.courierGeoAcquireRetryTimer)
      state.courierGeoAcquireRetryTimer = null
    }
  }

  function scheduleCourierGeoLeadershipAcquire(delayMs = COURIER_GEO_WATCH_RETRY_MS) {
    if (!state.courierGeoLeaderDesired) return
    if (state.courierGeoAcquireRetryTimer) return

    state.courierGeoAcquireRetryTimer = setTimeout(() => {
      state.courierGeoAcquireRetryTimer = null
      void ensureCourierGeoWatchLeadership()
    }, Math.max(250, Number(delayMs) || COURIER_GEO_WATCH_RETRY_MS))
  }

  function releaseCourierGeoLeaseStorage() {
    if (!canUseStorage()) return

    try {
      const tabId = ensureCrossTabRuntimeTabId()
      const raw = window.localStorage.getItem(COURIER_GEO_WATCH_LEASE_KEY)
      if (!raw) return
      const lease = JSON.parse(raw)
      if (lease?.ownerTabId !== tabId) return
      window.localStorage.removeItem(COURIER_GEO_WATCH_LEASE_KEY)
    } catch (_) {}
  }

  function markCourierGeoLeaderActive(mode = null) {
    const wasActive = state.courierGeoLeaderActive === true
    state.courierGeoLeaderActive = true
    state.courierGeoLeaderMode = mode || state.courierGeoLeaderMode || 'lease'
    if (!wasActive) {
      emitRuntimeSignal('geo_leader_acquired', state.courierGeoLeaderMode, {
        meta: { desired: state.courierGeoLeaderDesired },
      })
    }
    startCourierWatch()
  }

  function demoteCourierGeoLeader(reason = 'leader_demoted') {
    const wasActive = state.courierGeoLeaderActive === true
    const previousMode = state.courierGeoLeaderMode || 'none'
    state.courierGeoLeaderActive = false
    state.courierGeoLeaderMode = null
    if (wasActive) {
      emitRuntimeSignal('geo_leader_demoted', reason, {
        level: reason === 'leadership_stopped' ? 'info' : 'warn',
        meta: { previousMode },
      })
    }
    stopCourierWatch()
  }

  function tryAcquireCourierGeoLeaseStorage() {
    if (!canUseStorage()) return false

    const now = Date.now()
    const tabId = ensureCrossTabRuntimeTabId()

    try {
      const raw = window.localStorage.getItem(COURIER_GEO_WATCH_LEASE_KEY)
      const current = raw ? JSON.parse(raw) : null
      const leaseActive = current
        && typeof current.ownerTabId === 'string'
        && Number(current.expiresAt) > now

      if (leaseActive && current.ownerTabId !== tabId) return false

      const nextLease = {
        ownerTabId: tabId,
        acquiredAt: leaseActive && current.ownerTabId === tabId
          ? Number(current.acquiredAt) || now
          : now,
        updatedAt: now,
        expiresAt: now + COURIER_GEO_WATCH_LEASE_TTL_MS,
      }

      window.localStorage.setItem(COURIER_GEO_WATCH_LEASE_KEY, JSON.stringify(nextLease))

      const confirmedRaw = window.localStorage.getItem(COURIER_GEO_WATCH_LEASE_KEY)
      if (!confirmedRaw) return false
      const confirmed = JSON.parse(confirmedRaw)
      return confirmed?.ownerTabId === tabId && Number(confirmed.expiresAt) > now
    } catch (_) {
      return false
    }
  }

  function startCourierGeoLeaseHeartbeat() {
    if (state.courierGeoLeaseHeartbeatTimer) return

    state.courierGeoLeaseHeartbeatTimer = setInterval(() => {
      if (!state.courierGeoLeaderDesired) return

      const stillOwner = tryAcquireCourierGeoLeaseStorage()
      if (stillOwner) {
        if (!state.courierGeoLeaderActive) {
          markCourierGeoLeaderActive('lease')
        }
        return
      }

      if (state.courierGeoLeaderActive && state.courierGeoLeaderMode === 'lease') {
        demoteCourierGeoLeader('lease_heartbeat_lost')
      }

      scheduleCourierGeoLeadershipAcquire(700)
    }, COURIER_GEO_WATCH_HEARTBEAT_MS)
  }

  function stopCourierGeoLeaseHeartbeat() {
    if (!state.courierGeoLeaseHeartbeatTimer) return
    clearInterval(state.courierGeoLeaseHeartbeatTimer)
    state.courierGeoLeaseHeartbeatTimer = null
  }

  async function tryAcquireCourierGeoLeaderViaWebLocks() {
    if (!canUseWebLocks()) return false
    if (!state.courierGeoLeaderDesired) return false
    if (state.courierGeoLockAbortController) return state.courierGeoLeaderActive

    const controller = new AbortController()
    state.courierGeoLockAbortController = controller
    let granted = false

    try {
      await navigator.locks.request(
        'poof:courier-geo-watch:leader-lock:v1',
        { mode: 'exclusive', ifAvailable: true, signal: controller.signal },
        async (lock) => {
          if (!lock || !state.courierGeoLeaderDesired) return
          granted = true
          markCourierGeoLeaderActive('web-lock')

          await new Promise((resolve) => {
            controller.signal.addEventListener('abort', resolve, { once: true })
          })
        }
      )
    } catch (_) {
      // no-op: fall back handled by caller
    } finally {
      if (state.courierGeoLockAbortController === controller) {
        state.courierGeoLockAbortController = null
      }

      if (state.courierGeoLeaderMode === 'web-lock') {
        demoteCourierGeoLeader('web_lock_released')
      }
    }

    return granted
  }

  function releaseCourierGeoLeaderLock() {
    const controller = state.courierGeoLockAbortController
    state.courierGeoLockAbortController = null
    if (controller) {
      try { controller.abort() } catch (_) {}
    }
  }

  function bindCourierGeoLeadershipStorageOnce() {
    if (state.courierGeoStorageBound) return
    state.courierGeoStorageBound = true

    window.addEventListener('storage', (event) => {
      if (event.key !== COURIER_GEO_WATCH_LEASE_KEY) return
      if (!state.courierGeoLeaderDesired) return

      const tabId = ensureCrossTabRuntimeTabId()
      let incomingOwner = null
      let incomingExpiresAt = 0
      try {
        const lease = event.newValue ? JSON.parse(event.newValue) : null
        incomingOwner = lease?.ownerTabId || null
        incomingExpiresAt = Number(lease?.expiresAt) || 0
      } catch (_) {}

      if (incomingOwner && incomingOwner !== tabId && incomingExpiresAt > Date.now()) {
        if (state.courierGeoLeaderMode === 'lease' && state.courierGeoLeaderActive) {
          demoteCourierGeoLeader('lease_taken_by_peer_tab')
        }
        emitRuntimeSignal('geo_leader_failover_wait', 'peer_tab_active_lease', {
          meta: {
            retryInMs: Math.min(2500, Math.max(700, incomingExpiresAt - Date.now() + 120)),
          },
        })
        scheduleCourierGeoLeadershipAcquire(Math.min(2500, Math.max(700, incomingExpiresAt - Date.now() + 120)))
        return
      }

      scheduleCourierGeoLeadershipAcquire(500)
    })

    window.addEventListener('visibilitychange', () => {
      if (!state.courierGeoLeaderDesired) return
      if (document.visibilityState !== 'visible') return
      scheduleCourierGeoLeadershipAcquire(300)
    })

    window.addEventListener('beforeunload', () => {
      stopCourierGeoWatchLeadership()
    })
  }

  async function ensureCourierGeoWatchLeadership() {
    clearCourierGeoAcquireRetry()
    if (!state.courierGeoLeaderDesired) return

    if (canUseWebLocks()) {
      const lockGranted = await tryAcquireCourierGeoLeaderViaWebLocks()
      if (!state.courierGeoLeaderDesired) return
      if (lockGranted) return
    }

    const leaseGranted = tryAcquireCourierGeoLeaseStorage()

    if (leaseGranted) {
      markCourierGeoLeaderActive('lease')
      if (!canUseWebLocks()) {
        emitRuntimeSignal('geo_leader_fallback', 'web_locks_unavailable_using_lease')
      }
      startCourierGeoLeaseHeartbeat()
      return
    }

    if (state.courierGeoLeaderMode === 'lease' && state.courierGeoLeaderActive) {
      demoteCourierGeoLeader('lease_contention')
    }

    scheduleCourierGeoLeadershipAcquire()
  }

  function startCourierGeoWatchLeadership() {
    state.courierGeoLeaderDesired = true
    bindCourierGeoLeadershipStorageOnce()
    void ensureCourierGeoWatchLeadership()
  }

  function stopCourierGeoWatchLeadership() {
    state.courierGeoLeaderDesired = false
    clearCourierGeoAcquireRetry()
    stopCourierGeoLeaseHeartbeat()
    releaseCourierGeoLeaderLock()
    releaseCourierGeoLeaseStorage()
    demoteCourierGeoLeader('leadership_stopped')
  }

  function emitUserLocationBootstrapState() {
    window.dispatchEvent(new CustomEvent('poof:user-location-bootstrap', {
      detail: {
        isResolving: state.isResolvingUserLocation,
        hasResolved: state.hasResolvedUserLocation,
        location: getPersistedUserLocation(),
      },
    }))
  }

  function setUserLocationResolving(isResolving, options = {}) {
    state.isResolvingUserLocation = Boolean(isResolving)
    if (options.resolved === true) {
      state.hasResolvedUserLocation = true
    }
    emitUserLocationBootstrapState()
  }

  function persistUserLocation(lat, lng, meta = {}) {
    if (!isValidLatLng(lat, lng)) return null

    const location = {
      lat: Number(Number(lat).toFixed(6)),
      lng: Number(Number(lng).toFixed(6)),
      updatedAt: Date.now(),
      source: meta.source || 'unknown',
    }

    if (canUseStorage()) {
      try {
        window.localStorage.setItem(LAST_KNOWN_USER_LOCATION_KEY, JSON.stringify(location))
      } catch (_) {}
    }

    window.POOF.userLocation = { lat: location.lat, lng: location.lng }
    emitUserLocationBootstrapState()

    return location
  }

  function getPersistedUserLocation() {
    if (!canUseStorage()) return null

    try {
      const raw = window.localStorage.getItem(LAST_KNOWN_USER_LOCATION_KEY)
      if (!raw) return null
      const parsed = JSON.parse(raw)
      if (!isValidLatLng(parsed?.lat, parsed?.lng)) return null

      return {
        lat: Number(Number(parsed.lat).toFixed(6)),
        lng: Number(Number(parsed.lng).toFixed(6)),
        updatedAt: Number(parsed.updatedAt) || null,
        source: typeof parsed.source === 'string' ? parsed.source : 'persisted',
      }
    } catch (_) {
      return null
    }
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


  function distanceKm(lat1, lng1, lat2, lng2) {
    const earth = 6371
    const dLat = ((lat2 - lat1) * Math.PI) / 180
    const dLng = ((lng2 - lng1) * Math.PI) / 180

    const a =
      Math.sin(dLat / 2) ** 2 +
      Math.cos((lat1 * Math.PI) / 180) *
      Math.cos((lat2 * Math.PI) / 180) *
      Math.sin(dLng / 2) ** 2

    return earth * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)))
  }

  function coordinatesMatch(latA, lngA, latB, lngB, epsilon = 0.000001) {
    return Math.abs(Number(latA) - Number(latB)) <= epsilon
      && Math.abs(Number(lngA) - Number(lngB)) <= epsilon
  }

  function rememberPreferredVisibleAddressPoint(detail = {}) {
    const lat = toNumber(detail?.lat)
    const lng = toNumber(detail?.lng)

    if (!isValidLatLng(lat, lng)) return

    state.preferredVisibleAddressPoint = {
      lat,
      lng,
      label: toScalarString(detail?.label) || null,
      reason: toScalarString(detail?.reason) || 'visible-address',
      updatedAt: Number(detail?.updatedAt) || Date.now(),
    }
  }

  function rememberAuthoritativeAddressPickerPoint(detail = {}) {
    const lat = toNumber(detail?.lat)
    const lng = toNumber(detail?.lng)

    if (!isValidLatLng(lat, lng)) return

    state.authoritativeAddressPickerPoint = {
      lat,
      lng,
      label: toScalarString(detail?.label) || null,
      reason: toScalarString(detail?.reason) || 'authoritative-point',
      updatedAt: Number(detail?.updatedAt) || Date.now(),
    }
  }

  function shouldIgnoreIncomingAddressPickerSyncPoint(lat, lng, source = 'sync') {
    return shouldIgnoreStaleAddressPickerSyncPoint({
      isAddressPickerFlow: state.isAddressPickerFlow,
      lat,
      lng,
      source,
      authoritativePoint: state.authoritativeAddressPickerPoint,
      preferredPoint: state.preferredVisibleAddressPoint,
      now: Date.now(),
    })
  }

  function isCourierCoordsConfirmed(lat, lng, accuracy = null) {
    if (!isValidLatLng(lat, lng)) return false

    const maxAccuracyMeters = 120
    if (accuracy !== null && Number.isFinite(Number(accuracy)) && Number(accuracy) > maxAccuracyMeters) {
      return false
    }

    const orderLL = state.orderMarker?.getLatLng?.()
    if (!orderLL) return true

    const maxCityDistanceKm = 80
    return distanceKm(Number(lat), Number(lng), Number(orderLL.lat), Number(orderLL.lng)) <= maxCityDistanceKm
  }

  function dispatchMapUiError(message, options = {}) {
    if (!message || options.notify === false) return

    window.dispatchEvent(
      new CustomEvent('notify', {
        detail: [{ type: options.type || 'error', message }],
      })
    )

    if (options.useAlertFallback === true && typeof window.alert === 'function') {
      window.alert(message)
    }
  }

  function emitCourierGeoMarker(event, detail = {}, level = 'info') {
    window.dispatchEvent(new CustomEvent('poof:courier-geo-marker', {
      detail: {
        event,
        level,
        ts: Date.now(),
        ...detail,
      },
    }))
  }

  function emitGeoActionState(detail = {}) {
    window.dispatchEvent(new CustomEvent('poof:geo-action-state', { detail }))
  }

  function getGeolocationErrorMessage(error, options = {}) {
    if (!navigator.geolocation) {
      return 'Геолокація не підтримується вашим браузером.'
    }

    const bootstrapContext = 'Щоб показати локально релевантні адреси, дозвольте доступ до геолокації або введіть адресу вручну.'
    const actionContext = 'GPS на пристрої може бути увімкнений, але браузер або webview ще не мають дозволу. Дозвольте доступ у налаштуваннях браузера/застосунку або виберіть адресу вручну.'
    const context = options.context === 'bootstrap' ? bootstrapContext : actionContext

    switch (Number(error?.code)) {
      case error?.PERMISSION_DENIED:
      case 1:
        return `Не вдалося отримати доступ до геолокації. ${context}`
      case error?.TIMEOUT:
      case 3:
        return 'Не вдалося визначити локацію вчасно. Спробуйте ще раз або виберіть точку на мапі вручну.'
      case error?.POSITION_UNAVAILABLE:
      case 2:
      default:
        return 'Браузер або webview зараз не зміг отримати координати. Посуньте мапу вручну або повторіть спробу трохи пізніше.'
    }
  }

  function handleGeolocationError(error, options = {}) {
    const message = getGeolocationErrorMessage(error, options)
    const code = Number(error?.code) || null
    const permissionDenied = code === 1
    const deniedUiState = permissionDenied
      ? buildDeniedGeolocationUiState({
        source: options.source || 'unknown',
        message,
      })
      : null

    if (options.markResolved !== false) {
      setUserLocationResolving(false, { resolved: true })
    }

    dispatchMapUiError(message, {
      useAlertFallback: options.useAlertFallback === true,
      notify: options.notify !== false,
      type: options.type || 'error',
    })

    emitGeoActionState(permissionDenied
      ? {
        ...deniedUiState,
        code,
      }
      : {
        status: 'error',
        message,
        code,
        source: options.source || 'unknown',
      })

    if (options.log !== false && !permissionDenied) {
      console.error('Geolocation error', error)
    } else if (options.log !== false && permissionDenied) {
      console.warn('Geolocation denied by user/browser permissions', error)
    }

    emitCourierGeoMarker('geolocation_denied_or_error', {
      source: options.source || 'unknown',
      code,
      message,
    }, permissionDenied ? 'warn' : 'error')

    return message
  }

  function setGeoActionLoading(isLoading, message = '') {
    state.geoActionInFlight = Boolean(isLoading)
    emitGeoActionState({
      status: isLoading ? 'loading' : 'idle',
      message,
      source: 'action',
    })
  }

  function applyCurrentLocation(lat, lng, options = {}) {
    if (!isValidLatLng(lat, lng)) return false

    persistUserLocation(lat, lng, { source: options.persistSource || 'user' })
    setUserLocationResolving(false, { resolved: true })

    void updatePointAndAddress(lat, lng, {
      source: options.source || 'user',
      zoom: options.zoom ?? 18,
    })

    emitGeoActionState({
      status: 'success',
      message: options.message || 'Локацію оновлено. За потреби посуньте мапу, щоб уточнити точку.',
      source: options.source || 'user',
    })

    if (options.closeAddressBook === true) {
      window.dispatchEvent(new CustomEvent('close-address-book'))
    }

    return true
  }

  function usePersistedLocationFallback(options = {}) {
    const plan = buildCurrentLocationFallbackPlan({
      allowPersistedFallback: options.allowFallback === true,
      persistedLocation: getPersistedUserLocation(),
      closeAddressBook: options.closeAddressBook === true,
      source: options.source || 'user-fallback',
      zoom: options.zoom ?? 17,
    })

    if (!plan) return false

    emitRuntimeSignal('geo_self_heal_fallback_applied', plan.source || 'persisted_location_fallback', {
      level: 'warn',
      meta: {
        persistSource: plan.persistSource,
      },
    })

    return applyCurrentLocation(plan.lat, plan.lng, {
      source: plan.source,
      persistSource: plan.persistSource,
      zoom: plan.zoom,
      message: plan.message,
      closeAddressBook: plan.closeAddressBook,
    })
  }

  function requestCurrentLocation(options = {}) {
    if (!navigator.geolocation) {
      const message = 'Геолокація не підтримується вашим браузером.'
      dispatchMapUiError(message, { useAlertFallback: options.useAlertFallback === true, notify: options.notify !== false })
      emitGeoActionState({ status: 'error', message, source: options.source || 'action' })
      return
    }

    if (options.explicitAction) {
      setGeoActionLoading(true)
    }

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        if (options.explicitAction) {
          setGeoActionLoading(false)
        }

        const lat = pos.coords?.latitude
        const lng = pos.coords?.longitude

        if (!applyCurrentLocation(lat, lng, {
          source: options.successSource || 'user',
          persistSource: options.persistSource || 'user',
          zoom: options.zoom ?? 18,
          closeAddressBook: options.closeAddressBook === true,
        })) {
          handleGeolocationError({ code: 2 }, options)
          return
        }

        emitCourierGeoMarker('first_geolocation_payload_received', {
          source: options.successSource || options.source || 'user',
          lat: Number(lat),
          lng: Number(lng),
        })
      },
      async (error) => {
        if (options.explicitAction) {
          setGeoActionLoading(false)
        }

        if (usePersistedLocationFallback(options)) {
          const fallbackPlan = buildCurrentLocationFallbackPlan({
            allowPersistedFallback: options.allowFallback === true,
            persistedLocation: getPersistedUserLocation(),
            closeAddressBook: options.closeAddressBook === true,
            source: options.source || 'user-fallback',
            zoom: options.zoom ?? 17,
          })

          dispatchMapUiError(fallbackPlan?.warning || 'Точну локацію не вдалося отримати — використали останню збережену точку.', {
            type: 'warning',
            useAlertFallback: false,
            notify: options.notify !== false,
          })
          return
        }

        handleGeolocationError(error, options)
      },
      {
        enableHighAccuracy: options.enableHighAccuracy !== false,
        timeout: options.timeout ?? 12000,
        maximumAge: options.maximumAge ?? 0,
      }
    )
  }

  function debugMapFlow(event, payload = {}) {
    if (!DEBUG_MAP) return
    console.debug(`[POOF:map][debug] ${event}`, payload)
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

  function sendLocation(lat, lng, source = 'map') {
    if (!hasLivewire() || typeof window.Livewire.dispatch !== 'function') return

    // OrderCreate
    window.Livewire.dispatch('set-location', { lat, lng })

    // AddressForm (если есть)
    try {
      window.Livewire.dispatch('address:set-coords', {
        lat,
        lng,
        source,
      })
    } catch (_) {}
  }

  function syncAddressFormCoords(lat, lng, source = 'map') {
    if (!hasLivewire() || typeof window.Livewire.dispatch !== 'function') return

    try {
      window.Livewire.dispatch('address:set-coords', {
        lat,
        lng,
        source,
      })
    } catch (_) {}
  }

  function toScalarString(value) {
    if (typeof value === 'string') {
      return value.trim()
    }

    if (typeof value === 'number') {
      return String(value)
    }

    if (value && typeof value === 'object') {
      return String(
        value.label ??
        value.name ??
        value.value ??
        value.street ??
        value.road ??
        ''
      ).trim()
    }

    return ''
  }

  function normalizeAddressPayload(item, lat, lng) {
    if (!item || typeof item !== 'object') {
      console.warn('[POOF] Invalid reverse geocode item', item)
      return null
    }

    const street = toScalarString(item.street ?? item.road)
    const house = toScalarString(item.house ?? item.housenumber ?? item.house_number)
    const city = toScalarString(item.city ?? item.town ?? item.village)
    const region = toScalarString(item.region ?? item.state)

    const line1 = street
      ? [street, house].filter(Boolean).join(' ')
      : toScalarString(item.line1)

    const line2 = city
      ? [city, region].filter(Boolean).join(', ')
      : toScalarString(item.line2)

    const label =
      toScalarString(item.label) ||
      [line1, line2].filter(Boolean).join(', ')

    return {
      street: street || null,
      house: house || null,
      city: city || null,
      region: region || null,
      line1: line1 || null,
      line2: line2 || null,
      label: label || 'Unknown address',
      lat: Number(item.lat ?? lat),
      lng: Number(item.lng ?? lng),
    }
  }

  function shouldIgnoreIncomingRemotePoint(lat, lng, source = 'map') {
    if (source !== 'geolocation') {
      return false
    }

    if (state.markerPrecision !== 'exact') {
      return false
    }

    if (!isValidLatLng(state.lastLat, state.lastLng) || !isValidLatLng(lat, lng)) {
      return false
    }

    return Math.abs(Number(state.lastLat) - Number(lat)) > 0.000001
      || Math.abs(Number(state.lastLng) - Number(lng)) > 0.000001
  }

  async function reverseGeocodeAndDispatch(lat, lng, options = {}) {
    if (options?.source === 'autocomplete' || state.addressLocked || isSavedAddressLocked()) {
      if (DEBUG_MAP) {
        if (isSavedAddressLocked()) {
          console.debug('[POOF] reverse geocode blocked (saved address)')
        } else {
          console.debug('[POOF] reverse geocode skipped (locked/source)', options?.source)
        }
      }
      return
    }

    const requestId = ++state.reverseRequestId

    try {
      state.isReverseUpdating = true
      if (DEBUG_MAP) console.debug('[POOF] reverse geocode start', lat, lng)

      const response = await fetch(`${API_BASE}/api/geocode?lat=${lat}&lng=${lng}`)

      if (requestId !== state.reverseRequestId) return

      if (!response.ok) {
        emitRuntimeSignal('reverse_geocode_degraded', 'http_not_ok', {
          level: 'warn',
          meta: {
            status: Number(response.status),
            source: options?.source || 'map',
          },
        })
        if (DEBUG_MAP) console.warn('[POOF] reverse geocode failed', response.status)
        return
      }

      const data = await response.json()

      if (!Array.isArray(data) || data.length === 0) {
        emitRuntimeSignal('reverse_geocode_degraded', 'empty_result', {
          level: 'warn',
          meta: { source: options?.source || 'map' },
        })
        if (DEBUG_MAP) console.warn('[POOF] reverse geocode empty result')
        return
      }

      const raw = data[0]
      const result = normalizeAddressPayload(raw, lat, lng)

      if (!result) return

      if (DEBUG_MAP) console.debug('[POOF] normalized address', result)

      window.dispatchEvent(
        new CustomEvent('address:reverse-geocoded', {
          detail: { item: result },
        })
      )

      window.dispatchEvent(
        new CustomEvent('map:set-address', {
          detail: result,
        })
      )

    } catch (error) {
      emitRuntimeSignal('reverse_geocode_degraded', 'request_failed', {
        level: 'warn',
        meta: { source: options?.source || 'map' },
      })
      console.error('[POOF] reverse geocode error', error)
    } finally {
      if (requestId === state.reverseRequestId) {
        state.isReverseUpdating = false
      }
    }
  }

  function scheduleReverseGeocode(lat, lng, options = {}) {
    if (options?.source === 'autocomplete' || state.addressLocked || isSavedAddressLocked()) {
      if (DEBUG_MAP && isSavedAddressLocked()) {
        console.debug('[POOF] reverse geocode blocked (saved address)')
      }
      return
    }

    clearTimeout(state.reverseDebounceTimer)
    state.reverseDebounceTimer = setTimeout(() => {
      void reverseGeocodeAndDispatch(lat, lng, options)
    }, 500)
  }

  async function updatePointAndAddress(lat, lng, { source = 'user', zoom = 18 } = {}) {
    if (shouldIgnoreIncomingRemotePoint(lat, lng, source)) {
      if (DEBUG_MAP) console.debug('[POOF] stale remote point ignored', { lat, lng, source })
      return
    }

    setMarker(lat, lng, {
      emit: true,
      zoom,
      source,
    })

    await reverseGeocodeAndDispatch(lat, lng, { source })
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

  function clearFloatingMarker(map = state.instance) {
    if (!window.POOF.marker || !map) {
      window.POOF.marker = null
      state.marker = null
      return
    }

    if (map.hasLayer(window.POOF.marker)) {
      try { map.removeLayer(window.POOF.marker) } catch (_) {}
    }

    window.POOF.marker = null
    state.marker = null
  }

  // ------------------------------------------------------------
  // CORE: marker API (pendingPoint safe)
  // ------------------------------------------------------------
  function setMarker(lat, lng, options = {}) {
    if (!leafletReady()) return

    const latN = toNumber(lat)
    const lngN = toNumber(lng)
    if (latN === null || lngN === null) return

    // карта ещё не готова — сохраняем точку
    if (!state.instance) {
      state.pendingPoint = { lat: latN, lng: lngN, zoom: options.zoom ?? 18 }
      return
    }

    const map = window.POOF?.map?.instance
    if (!map) return

    const zoom = options.zoom ?? map.getZoom()
    const emit = options.emit ?? false
    const source = typeof options.source === 'string' ? options.source : 'user'

    if (shouldIgnoreIncomingAddressPickerSyncPoint(latN, lngN, source)) {
      if (DEBUG_MAP) {
        console.debug('[POOF] stale sync marker ignored in address picker', {
          lat: latN,
          lng: lngN,
          source,
          authoritative: state.authoritativeAddressPickerPoint,
          preferred: state.preferredVisibleAddressPoint,
        })
      }
      return
    }

    if (state.isAddressPickerFlow && ['geolocation', 'user', 'autocomplete'].includes(source)) {
      rememberAuthoritativeAddressPickerPoint({
        lat: latN,
        lng: lngN,
        reason: source,
      })
    }

    if (DEBUG_MAP) {
      console.debug('[POOF MAP] setMarker', latN, lngN)
      console.debug('[POOF MAP] update', latN, lngN, options)
    }

    state.lastLat = latN
    state.lastLng = lngN

    if (!state.isAddressPickerFlow) {
      // create marker if it doesn't exist
      if (!window.POOF.marker) {
        const icon = getMarkerIcon()
        window.POOF.marker = window.L.marker([latN, lngN], {
          draggable: true,
          icon: icon || undefined,
        }).addTo(map)

        window.POOF.marker.on('dragend', (e) => {
          const p = e.target.getLatLng()
          void updatePointAndAddress(p.lat, p.lng, {
            source: 'user',
            zoom: map?.getZoom() || 18,
          })
        })
      }

      window.POOF.marker.setLatLng([latN, lngN])
      const icon = getMarkerIcon()
      if (icon) window.POOF.marker.setIcon(icon)

      state.marker = window.POOF.marker
    } else {
      clearFloatingMarker(map)
    }

    // ensure map centers on the marker (except courier location streaming updates)
    if (map && options?.source !== 'courier') {
      map.flyTo([latN, lngN], zoom, {
        animate: true,
        duration: 0.8,
      })
    }

    if (emit) sendLocation(latN, lngN, source || 'map')
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
    const payloadAccuracy = Number.isFinite(Number(payload.accuracy))
      ? Number(payload.accuracy)
      : null
    const courierConfirmedPayload = payload.courierConfirmed === true

    if (payload.source) {
      debugMapFlow('source update', { source: payload.source })
    }

    // if courier invalid but we have last good — use it
    let courierLatUse = courierLat
    let courierLngUse = courierLng
    let accuracyUse = payloadAccuracy

    if (!hasCourier) {
      const last = getLastGoodCourier()
      if (last) {
        courierLatUse = last.lat
        courierLngUse = last.lng
        accuracyUse = state.courierLastAccuracy
      }
    }

    const hasCourierEffective = isValidLatLng(courierLatUse, courierLngUse)
    if (!hasCourierEffective && !hasOrder) {
      const shouldWarnDefaultFallback = shouldShowDefaultCityUnconfirmedState({
        hasOrder,
        hasCourierCoords: hasCourierEffective,
        courierConfirmed: false,
      })

      if (shouldWarnDefaultFallback && !state.defaultCityFallbackLogged) {
        state.defaultCityFallbackLogged = true
        emitRuntimeSignal('map_default_city_used', 'courier_unconfirmed_no_coords', {
          level: 'warn',
          meta: {
            source: payload.source || 'unknown',
          },
        })
        emitCourierGeoMarker('map_fallback_default_city_used', {
          source: payload.source || 'unknown',
          reason: 'courier_unconfirmed_no_coords',
        }, 'warn')
        dispatchMapUiError('Фактична локація курʼєра не підтверджена. Мапа показує місто за замовчуванням, доки браузер не надасть геолокацію.', {
          type: 'warning',
        })
      }
      return
    }

    state.defaultCityFallbackLogged = false

    const lifecycle = resolveCourierMarkerLifecycle({
      isAddressPickerFlow: state.isAddressPickerFlow,
      hasCourier: hasCourierEffective,
      hasOrder,
    })

    if (lifecycle.shouldClearFloatingMarker) {
      clearFloatingMarker(state.instance)
    }

    // Courier marker + radius
    if (lifecycle.shouldRenderCourierMarker) {
      const courierLL = window.L.latLng(Number(courierLatUse), Number(courierLngUse))

      if (!state.debugFirstCourierCoordsLogged) {
        state.debugFirstCourierCoordsLogged = true
        debugMapFlow('first courier coords', {
          courierLat: Number(courierLatUse),
          courierLng: Number(courierLngUse),
          accuracy: accuracyUse,
          courierConfirmedPayload,
        })
      }

      // remember last good
      if (isCourierCoordsConfirmed(courierLatUse, courierLngUse, accuracyUse) || courierConfirmedPayload) {
        saveLastGoodCourier(courierLatUse, courierLngUse)
      }

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
      if (!state.radiusCircle && lifecycle.shouldRenderRadiusCircle) {
        state.radiusCircle = window.L.circle(courierLL, {
          radius: rMeters,
          weight: 1,
          fillOpacity: 0.08,
        }).addTo(state.instance)
      } else if (state.radiusCircle) {
        state.radiusCircle.setLatLng(courierLL)
        state.radiusCircle.setRadius(rMeters)
      }

      const courierConfirmed = courierConfirmedPayload || isCourierCoordsConfirmed(courierLatUse, courierLngUse, accuracyUse)
      state.courierConfirmed = courierConfirmed
      debugMapFlow('courier confirmation update', {
        courierConfirmed,
        payloadConfirmed: courierConfirmedPayload,
        accuracy: accuracyUse,
      })

      if (!state.__courierCenteredOnce && courierConfirmed && !hasOrder) {
        state.__courierCenteredOnce = true
        state.instance.setView(courierLL, 15, { animate: false })
      }
    }

    // Order marker
    if (lifecycle.shouldRenderOrderMarker) {
      const orderLL = window.L.latLng(Number(orderLat), Number(orderLng))

      if (!state.debugFirstOrderCoordsLogged) {
        state.debugFirstOrderCoordsLogged = true
        debugMapFlow('first order coords', {
          orderLat: Number(orderLat),
          orderLng: Number(orderLng),
        })
      }

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

      const courierConfirmed = courierConfirmedPayload || (hasCourierEffective && isCourierCoordsConfirmed(courierLatUse, courierLngUse, accuracyUse))
      if (!state.__courierCenteredOnce) {
        state.__courierCenteredOnce = true

        if (courierConfirmed && hasCourierEffective) {
          const bounds = window.L.latLngBounds([
            [Number(courierLatUse), Number(courierLngUse)],
            [Number(orderLat), Number(orderLng)],
          ])

          state.instance.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 })
        } else {
          state.instance.setView(orderLL, 16, { animate: false })
        }
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

    const routeDistanceKm = distanceKm(fLat, fLng, tLat, tLng)
    if (routeDistanceKm > 80) {
        console.warn('Route rejected due to abnormal courier distance', routeDistanceKm)
        dispatchMapUiError('Локація курʼєра не підтверджена')
        return;
    }

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

  function clearRouteOverlays() {
    if (!state.instance) return

    if (state.routeOutline) {
      try { state.instance.removeLayer(state.routeOutline) } catch (_) {}
      state.routeOutline = null
    }

    if (state.routeLine) {
      try { state.instance.removeLayer(state.routeLine) } catch (_) {}
      state.routeLine = null
    }
  }

  function resetMapStateForNavigation() {
    clearTimeout(state.reverseDebounceTimer)
    state.reverseDebounceTimer = null
    state.reverseRequestId += 1
    state.addressLocked = false
    state.pendingPoint = null

    state.debugBootstrapLogged = false
    state.debugFirstCourierCoordsLogged = false
    state.debugFirstOrderCoordsLogged = false
    state.hasActiveOrderBootstrap = false
  }

  function teardownMapInstance() {
    if (state.instance) {
      clearRouteOverlays()
      clearCourierOverlays()

      try {
        state.instance.off()
        state.instance.remove()
      } catch (_) {}
    }

    stopObserveResize()

    state.instance = null
    state.el = null
    state.marker = null
    state.tiles = null
    state.geoBtnBoundEl = null
    state.__courierCenteredOnce = false
    state.lastLat = null
    state.lastLng = null
    state.isResolvingUserLocation = false
    state.hasResolvedUserLocation = false

    window.POOF.marker = null
  }

  // ------------------------------------------------------------
  // Geo button (optional)
  // ------------------------------------------------------------
  function bindGeoButton() {
    const btn = document.getElementById('use-location-btn')
    if (!btn || state.geoBtnBoundEl === btn) return

    state.geoBtnBoundEl = btn

    btn.addEventListener('click', () => {
      if (state.geoActionInFlight) return

      requestCurrentLocation({
        explicitAction: true,
        useAlertFallback: true,
        notify: true,
        source: 'button',
        successSource: 'user',
        persistSource: 'user',
        timeout: 12000,
        maximumAge: 0,
        closeAddressBook: false,
      })
    })
  }


  function tryLocateUserForAddressModal() {
    if (!navigator.geolocation) return
    if (isSavedAddressLocked()) return
    if (state.lastLat !== null && state.lastLng !== null) return

    setUserLocationResolving(true)

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const lat = pos.coords?.latitude
        const lng = pos.coords?.longitude
        if (!isValidLatLng(lat, lng)) {
          setUserLocationResolving(false, { resolved: true })
          return
        }

        persistUserLocation(lat, lng, { source: 'user' })
        setUserLocationResolving(false, { resolved: true })

        updatePointAndAddress(lat, lng, {
          source: 'user',
          zoom: 17,
        })
      },
      (error) => {
        handleGeolocationError(error, { context: 'bootstrap', log: false, notify: false, source: 'bootstrap' })
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
    )
  }

  // ------------------------------------------------------------
  // Mount / Remount
  // ------------------------------------------------------------
  function destroyIfDomChanged(el) {
    const domChanged = state.el && state.el !== el
    if (!state.instance || !domChanged) return

    teardownMapInstance()
  }

  function hasActiveOrderBootstrapPayload(bootstrap = null) {
    return isValidLatLng(bootstrap?.orderLat, bootstrap?.orderLng)
  }

  function applyBootstrapFromDom(mapId = null) {
    const mapCardEl = mapId
      ? document.querySelector(`#${mapId}[data-map-bootstrap]`) || document.querySelector(`#${mapId}`)?.closest('[data-map-bootstrap]')
      : document.querySelector('[data-map-bootstrap]')
    const bootstrapRaw = mapCardEl?.dataset?.mapBootstrap || null
    state.hasActiveOrderBootstrap = false
    if (!bootstrapRaw) return false

    try {
      const bootstrap = JSON.parse(bootstrapRaw)
      if (!state.debugBootstrapLogged) {
        state.debugBootstrapLogged = true
        debugMapFlow('bootstrap payload', bootstrap)
      }

      state.hasActiveOrderBootstrap = hasActiveOrderBootstrapPayload(bootstrap)

      if (resolveMapBootstrapRejectionReason(bootstrap) === null) {
        setCourierMap({
          orderLat: bootstrap.orderLat,
          orderLng: bootstrap.orderLng,
          courierLat: bootstrap.courierLat,
          courierLng: bootstrap.courierLng,
          courierConfirmed: bootstrap.courierConfirmed === true,
          radiusKm: 5,
          source: 'bootstrap',
        })
        return true
      }

      emitRuntimeSignal('map_bootstrap_rejected', 'invalid_or_stale_coords', {
        level: 'warn',
        meta: {
          source: 'dom_bootstrap',
          hasOrderCoords: isFiniteLatLngForBootstrap(bootstrap?.orderLat, bootstrap?.orderLng),
          hasCourierCoords: isFiniteLatLngForBootstrap(bootstrap?.courierLat, bootstrap?.courierLng),
        },
      })
    } catch (error) {
      emitRuntimeSignal('map_bootstrap_rejected', 'invalid_payload', {
        level: 'warn',
        meta: { source: 'dom_bootstrap' },
      })
      console.warn('[POOF] invalid map bootstrap payload', error)
    }

    return false
  }

  function mount(mapId = 'map') {
    if (isRuntimeBlockedByAuthLoss()) return false
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
      applyBootstrapFromDom(mapId)
      try { state.instance.invalidateSize(true) } catch (_) {}
      observeMapResize(el, state.instance)
      bindGeoButton()
      return true
    }

    state.el = el

    const isAddressPickerFlow = Boolean(el.closest('#address-form'))
    state.isAddressPickerFlow = isAddressPickerFlow

    state.instance = window.L.map(el, {
      zoomControl: !isAddressPickerFlow,
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

    if (isAddressPickerFlow) {
      clearFloatingMarker(state.instance)
    }

    state.instance.on('moveend', async () => {
      const center = state.instance.getCenter()
      const lat = center.lat
      const lng = center.lng

      state.lastLat = Number(lat)
      state.lastLng = Number(lng)

      if (state.isAddressPickerFlow && !isSavedAddressLocked()) {
        state.addressLocked = false
        window.dispatchEvent(new CustomEvent('address:unlock', {
          detail: { reason: 'map-move' },
        }))
      }

      window.dispatchEvent(
        new CustomEvent('poof:map-center-changed', {
          detail: {
            lat,
            lng,
          },
        })
      )

      syncAddressFormCoords(lat, lng, 'map')
      scheduleReverseGeocode(lat, lng, { source: 'map-move' })
    })

    if (!isAddressPickerFlow) {
      state.instance.on('click', function (e) {
        const lat = e.latlng.lat
        const lng = e.latlng.lng

        state.addressLocked = false
        window.dispatchEvent(new CustomEvent('address:unlock', {
          detail: { reason: 'map-click' },
        }))

        void updatePointAndAddress(lat, lng, {
          source: 'user',
          zoom: state.instance?.getZoom() || 18,
        })
      })
    }

    // применяем pendingPoint (если события пришли раньше)
    if (state.pendingPoint) {
      const { lat, lng, zoom } = state.pendingPoint
      state.pendingPoint = null
      setMarker(lat, lng, { emit: false, zoom: zoom ?? 18, source: 'sync' })
    }

    const mapEl = document.getElementById('map')

    if (mapEl) {
      const lat = parseFloat(mapEl.dataset.lat)
      const lng = parseFloat(mapEl.dataset.lng)

      if (isValidLatLng(lat, lng)) {
        if (DEBUG_MAP) console.debug('[POOF] center map from dataset', lat, lng)
        setMarker(lat, lng, { emit: false, zoom: 17, source: 'saved-address' })
        state.instance.setView([lat, lng], 17, { animate: false })
      }
    }

    applyBootstrapFromDom(mapId)

    // post-mount invalidate (важно когда карта появляется после toggle)
    requestAnimationFrame(() => {
      setTimeout(() => {
        try { state.instance?.invalidateSize(true) } catch (_) {}
        document.getElementById('map-skeleton')?.remove()
      }, 50)
    })

    setTimeout(() => {
      if (window.POOF?.map?.instance) {
        window.POOF.map.instance.invalidateSize()
      }
    }, 200)

    bindGeoButton()
    return true
  }

  function mountAny() {
    if (isRuntimeBlockedByAuthLoss()) return false
    // пробуем основные id (у тебя встречались оба)
    return mount('map') || mount('my-orders-map') || mount('map-address')
  }

  // ------------------------------------------------------------
  // Courier geolocation watch
  // ------------------------------------------------------------
  function startCourierWatch() {
    if (isRuntimeBlockedByAuthLoss()) return
    if (!state.courierGeoLeaderActive) return
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

        const hasAccuracy = Number.isFinite(Number(accuracy))
        if (hasAccuracy && Number(accuracy) > 120) return

        const previous = getLastGoodCourier()
        if (previous) {
          const jumpKm = distanceKm(previous.lat, previous.lng, Number(lat), Number(lng))
          const positionTs = Number(pos.timestamp || Date.now())
          const deltaSec = Math.max(1, (positionTs - (state.lastGoodCourierAt || positionTs)) / 1000)
          const speedKmh = (jumpKm / deltaSec) * 3600
          if (jumpKm > 3 && speedKmh > 180) {
            console.warn('[POOF:map] abnormal courier jump ignored', { jumpKm, speedKmh, lat, lng })
            return
          }
        }

        // --------- ADDED (prod): throttle to avoid jitter & unnecessary LW spam ---------
        const now = Date.now()
        if (now - state.courierLastEmitAt < 700) return
        state.courierLastEmitAt = now

        const courierConfirmed = isCourierCoordsConfirmed(lat, lng, accuracy)
        if (courierConfirmed) {
          saveLastGoodCourier(lat, lng)
          state.courierLastAccuracy = hasAccuracy ? Number(accuracy) : null
        }

        // 1) обновляем БД только подтвержденными координатами
        if (courierConfirmed && window.Livewire?.dispatch) {
          window.Livewire.dispatch('courier-location', { lat, lng, accuracy })
        }

        // 2) обновляем карту
        setCourierMap({
          courierLat: lat,
          courierLng: lng,
          accuracy,
          courierConfirmed,
          radiusKm: 5,
          source: 'watchPosition',
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
  bindCrossTabRuntimeSyncOnce()

  // PHP/Browser → JS: set location from autocomplete or sync
  window.addEventListener('map:set-location', (event) => {
    const { lat, lng, zoom, source } = event.detail || {}

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
      return
    }

    if (window.POOF?.setMarker) {
      if (source !== 'autocomplete') {
        state.addressLocked = false
      }

      window.POOF.setMarker(lat, lng, { zoom, source })
    }
  })

  // PHP → JS: marker set
  window.addEventListener('map:set-marker', (e) => {
    const lat = e.detail?.lat
    const lng = e.detail?.lng
    const source = e.detail?.source ?? 'sync'
    const zoom = Number.isFinite(Number(e.detail?.zoom)) ? Number(e.detail.zoom) : 18

    if (lat == null || lng == null) return

    if (shouldIgnoreIncomingAddressPickerSyncPoint(lat, lng, source)) {
      if (DEBUG_MAP) {
        console.debug('[POOF] stale sync point ignored in address picker', {
          lat,
          lng,
          source,
          authoritative: state.authoritativeAddressPickerPoint,
          preferred: state.preferredVisibleAddressPoint,
        })
      }
      return
    }

    if (source === 'autocomplete') {
      state.addressLocked = true
      rememberAuthoritativeAddressPickerPoint({
        lat,
        lng,
        reason: 'autocomplete',
      })
      rememberPreferredVisibleAddressPoint({
        lat,
        lng,
        reason: 'autocomplete-marker',
      })
      setMarker(lat, lng, { emit: false, zoom, source })
      return
    }

    if (source === 'geolocation' || source === 'user') {
      state.addressLocked = false
      rememberAuthoritativeAddressPickerPoint({
        lat,
        lng,
        reason: source,
      })
      rememberPreferredVisibleAddressPoint({
        lat,
        lng,
        reason: source,
      })
      void updatePointAndAddress(lat, lng, {
        source,
        zoom,
      })
      return
    }

    setMarker(lat, lng, {
      emit: false,
      zoom,
      source: source === 'autocomplete' ? 'autocomplete' : 'sync',
    })
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
    if (isRuntimeBlockedByAuthLoss()) return
    setTimeout(() => {
      mountAny()
      try { state.instance?.invalidateSize(true) } catch (_) {}
      startCourierGeoWatchLeadership()
    }, 0)
  })

  window.addEventListener('courier:offline', () => {
    stopCourierGeoWatchLeadership()
  })

  window.addEventListener('courier-online-toggled', (e) => {
    let payload = e.detail
    if (Array.isArray(payload)) payload = payload[0] || {}

    emitCrossTabCourierRuntimeSync(payload || {}, { reason: 'courier_online_toggled' })
  })

  window.addEventListener('courier:runtime-sync', (e) => {
    if (isRuntimeBlockedByAuthLoss()) return
    let payload = e.detail
    if (Array.isArray(payload)) payload = payload[0] || {}

    if (payload?.__crossTab !== true) {
      emitCrossTabCourierRuntimeSync(payload || {}, { reason: 'courier_runtime_sync' })
    }

    const isOnline = Boolean(payload?.online)

    if (isOnline) {
      mountAny()
      try { state.instance?.invalidateSize(true) } catch (_) {}
      startCourierGeoWatchLeadership()
      return
    }

    stopCourierGeoWatchLeadership()
  })

  window.addEventListener('poof:auth-session-lost', (event) => {
    if (state.authSessionLost) return
    state.authSessionLost = true

    const reason = normalizeRuntimeObservabilityReason(event?.detail?.reason, 'auth_session_lost')
    emitRuntimeSignal('auth_session_lost_runtime_teardown', reason, {
      level: 'warn',
      meta: {
        source: event?.detail?.source || 'unknown',
      },
    })

    stopCourierGeoWatchLeadership()
    resetMapStateForNavigation()
    teardownMapInstance()
  })

  // -------------------------------
  // UPDATE COURIER MAP
  // -------------------------------
  window.addEventListener('map:courier-update', (e) => {
    if (isRuntimeBlockedByAuthLoss()) return
    mountAny()
    if (!state.instance) return
    setCourierMap(e.detail || {})
  })

  window.addEventListener('map:ui-error', (e) => {
    const message = e?.detail?.[0]?.message ?? e?.detail?.message ?? null
    if (!message) return
    dispatchMapUiError(message)
  })

  window.addEventListener('address:lock', () => {
    state.addressLocked = true
  })

  window.addEventListener('address:unlock', () => {
    state.addressLocked = false
  })

  window.addEventListener('poof:address-picker-visible-point', (event) => {
    rememberPreferredVisibleAddressPoint(event.detail || {})
  })

  window.addEventListener('use-current-location', () => {
    if (state.geoActionInFlight) return

    window.__poofUseCurrentLocationPending = false
    emitCourierGeoMarker('client_use_current_location_triggered', {
      source: 'window_event',
    })

    requestCurrentLocation({
      explicitAction: true,
      useAlertFallback: true,
      notify: true,
      source: 'event',
      successSource: 'user',
      persistSource: 'user',
      timeout: 8000,
      maximumAge: 0,
      closeAddressBook: true,
    })
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

    if (DEBUG_MAP) console.debug('ROUTE PAYLOAD FIXED:', payload)

    buildRoute(
        payload.fromLat,
        payload.fromLng,
        payload.toLat,
        payload.toLng
    )
})

  window.addEventListener('poof:sheet-opened', (e) => {
    if (e.detail?.name !== 'addressForm') return
    setTimeout(() => {
      mountAny()
      try { state.instance?.invalidateSize(true) } catch (_) {}
      tryLocateUserForAddressModal()
    }, 0)
  })

  // -------------------------------
  // FORCE MAP INIT
  // -------------------------------
  window.addEventListener('map:init', () => {
    if (isRuntimeBlockedByAuthLoss()) return
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
      if (isRuntimeBlockedByAuthLoss()) return
      mountAny()
      try { state.instance?.invalidateSize(true) } catch (_) {}
    })

    window.Livewire.hook('morph.added', () => {
      if (isRuntimeBlockedByAuthLoss()) return
      mountAny()
      try { state.instance?.invalidateSize(true) } catch (_) {}
    })
  }

  document.addEventListener('livewire:navigated', () => {
    if (isRuntimeBlockedByAuthLoss()) return
    resetMapStateForNavigation()
    teardownMapInstance()
    mountAny()
    applyBootstrapFromDom()
    try { state.instance?.invalidateSize(true) } catch (_) {}
  })
}

  // ------------------------------------------------------------
  // Public API
  // ------------------------------------------------------------
  POOF.initMap = mount
  POOF.mountMapAny = mountAny

  POOF.setCourierMap = (payload) => {
    if (isRuntimeBlockedByAuthLoss()) return
    mountAny()
    setCourierMap(payload || {})
  }

  POOF.setMarker = (lat, lng, options) => {
    const hasOptions = typeof options === 'object' && options !== null

    return setMarker(lat, lng, {
      emit: hasOptions ? (options.emit ?? false) : true,
      zoom: hasOptions ? (options.zoom ?? 18) : 18,
      source: hasOptions ? (options.source ?? 'user') : 'user',
    })
  }
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
  POOF.__getCourierRuntimeSignalCounters = () => ({ ...state.runtimeSignalCounters })
  POOF.__getCourierRuntimeEvidenceSync = () => buildCourierRuntimeEvidenceView({
    counters: state.runtimeSignalCounters,
    signals: state.runtimeSignalHistory,
    authSessionLost: state.authSessionLost,
    crossTab: {
      tabId: ensureCrossTabRuntimeTabId(),
      lastSignature: state.crossTabRuntimeLastSignature,
      lastEmittedAt: state.crossTabRuntimeLastEmittedAt,
      channelEnabled: Boolean(state.crossTabRuntimeChannel),
    },
    geoLeadership: {
      desired: state.courierGeoLeaderDesired,
      active: state.courierGeoLeaderActive,
      mode: state.courierGeoLeaderMode,
    },
  })
  POOF.__getCourierRuntimeEvidence = async (options = {}) => {
    const includeServerRuntime = options && options.includeServerRuntime === true
    let serverRuntime = null
    let serverRuntimeError = null

    if (includeServerRuntime) {
      try {
        const response = await fetch(`${API_BASE}/api/courier/runtime`, {
          method: 'GET',
          headers: {
            Accept: 'application/json',
          },
          credentials: 'include',
        })

        if (!response.ok) {
          serverRuntimeError = `http_${response.status}`
        } else {
          const payload = await response.json()
          serverRuntime = payload && typeof payload === 'object' ? (payload.runtime || null) : null
        }
      } catch (_) {
        serverRuntimeError = 'request_failed'
      }
    }

    return buildCourierRuntimeEvidenceView({
      counters: state.runtimeSignalCounters,
      signals: state.runtimeSignalHistory,
      authSessionLost: state.authSessionLost,
      crossTab: {
        tabId: ensureCrossTabRuntimeTabId(),
        lastSignature: state.crossTabRuntimeLastSignature,
        lastEmittedAt: state.crossTabRuntimeLastEmittedAt,
        channelEnabled: Boolean(state.crossTabRuntimeChannel),
      },
      geoLeadership: {
        desired: state.courierGeoLeaderDesired,
        active: state.courierGeoLeaderActive,
        mode: state.courierGeoLeaderMode,
      },
      serverRuntime,
      serverRuntimeError,
    })
  }
  POOF.__printCourierRuntimeEvidence = async (options = {}) => {
    const evidence = await POOF.__getCourierRuntimeEvidence(options)
    if (typeof console !== 'undefined' && typeof console.table === 'function') {
      console.table(evidence.topCounters)
      console.table(evidence.recentSignals.map((signal) => ({
        ts: signal.ts,
        level: signal.level,
        event: signal.event,
        reason: signal.reason,
      })))
    }
    return evidence
  }

  // ------------------------------------------------------------
  // Bootstrap
  // ------------------------------------------------------------
  bindGlobalHandlersOnce()
  if (window.__poofUseCurrentLocationPending === true) {
    window.__poofUseCurrentLocationPending = false
    setTimeout(() => {
      window.dispatchEvent(new CustomEvent('use-current-location'))
    }, 0)
  }
  if (!state.runtimeEvidenceRequestBound) {
    state.runtimeEvidenceRequestBound = true
    window.addEventListener('poof:courier-runtime-evidence-request', async (event) => {
      const detail = event?.detail && typeof event.detail === 'object' ? event.detail : {}
      const includeServerRuntime = detail.includeServerRuntime === true
      const evidence = await POOF.__getCourierRuntimeEvidence({ includeServerRuntime })
      window.dispatchEvent(new CustomEvent('poof:courier-runtime-evidence', { detail: evidence }))
    })
  }
  mountAny()
  const bootstrapApplied = applyBootstrapFromDom()
  const persistedUserLocation = getPersistedUserLocation()
  const shouldBootstrapFromPersistedLocation = shouldApplyPersistedLocationOnBootstrap({
    persistedLocation: persistedUserLocation,
    bootstrapApplied,
    hasActiveOrderBootstrap: state.hasActiveOrderBootstrap,
    isAddressPickerFlow: state.isAddressPickerFlow,
  })

  if (shouldBootstrapFromPersistedLocation) {
    state.addressLocked = false
    setMarker(persistedUserLocation.lat, persistedUserLocation.lng, { emit: false, zoom: 17, source: 'persisted-user-location' })

    try {
      state.instance?.setView([persistedUserLocation.lat, persistedUserLocation.lng], 17, { animate: false })
    } catch (_) {}
  }

  emitUserLocationBootstrapState()

  if (navigator.geolocation && !isSavedAddressLocked() && !bootstrapApplied && !state.hasActiveOrderBootstrap) {
    setUserLocationResolving(true)

    navigator.geolocation.getCurrentPosition((pos) => {
      const lat = pos.coords.latitude
      const lng = pos.coords.longitude

      if (!isValidLatLng(lat, lng)) {
        setUserLocationResolving(false, { resolved: true })
        return
      }

      if (DEBUG_MAP) console.debug('[POOF] user geolocation', lat, lng)

      persistUserLocation(lat, lng, { source: 'geolocation' })

      if (state.addressLocked) {
        setUserLocationResolving(false, { resolved: true })
        return
      }

      state.addressLocked = false
      setUserLocationResolving(false, { resolved: true })
      void updatePointAndAddress(lat, lng, {
        source: 'geolocation',
        zoom: 17,
      })
    }, (error) => {
      handleGeolocationError(error, { context: 'bootstrap', log: false, notify: false, source: 'bootstrap' })
    }, { enableHighAccuracy: true, timeout: 12000, maximumAge: persistedUserLocation ? 60000 : 0 })
  } else {
    setUserLocationResolving(false, { resolved: Boolean(persistedUserLocation) || isSavedAddressLocked() || bootstrapApplied || state.hasActiveOrderBootstrap })
  }

  POOF.getLastKnownUserLocation = getPersistedUserLocation
}

export {
  buildCurrentLocationFallbackPlan,
  buildCourierRuntimeEvidenceView,
  normalizeRuntimeObservabilityReason,
  normalizeIncomingCourierRuntimeSyncMessage,
  buildCourierRuntimeSyncEnvelope,
  resolveCourierMarkerLifecycle,
  shouldIgnoreStaleAddressPickerSyncPoint,
  shouldApplyPersistedLocationOnBootstrap,
}
