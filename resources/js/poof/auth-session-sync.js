const AUTH_SYNC_CHANNEL = 'poof:auth-session-sync:v1'
const AUTH_SYNC_STORAGE_KEY = 'poof:auth-session-sync:message:v1'
const AUTH_SYNC_TAB_ID_STORAGE_KEY = 'poof:auth-session-tab-id:v1'
const AUTH_SYNC_RELOAD_DEBOUNCE_MS = 1200

function canUseStorage() {
  try {
    return typeof window !== 'undefined' && typeof window.localStorage !== 'undefined'
  } catch (_) {
    return false
  }
}

function canUseBroadcastChannel() {
  try {
    return typeof window !== 'undefined' && typeof window.BroadcastChannel !== 'undefined'
  } catch (_) {
    return false
  }
}

function normalizeAuthSyncReason(reason, fallback = 'auth_state_changed') {
  if (typeof reason !== 'string') return fallback
  const normalized = reason.trim()
  return normalized !== '' ? normalized : fallback
}

function isSessionLossStatus(status) {
  return Number(status) === 401 || Number(status) === 419
}

export default function initAuthSessionSync() {
  if (typeof window === 'undefined' || window.__poofAuthSessionSyncBound) return
  window.__poofAuthSessionSyncBound = true

  const state = {
    tabId: null,
    channel: null,
    lastHandledTs: 0,
    shouldReload: false,
  }

  function ensureTabId() {
    if (state.tabId) return state.tabId

    const generated = `tab-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`

    try {
      const existing = window.sessionStorage?.getItem(AUTH_SYNC_TAB_ID_STORAGE_KEY)
      if (existing) {
        state.tabId = existing
        return existing
      }
      window.sessionStorage?.setItem(AUTH_SYNC_TAB_ID_STORAGE_KEY, generated)
    } catch (_) {}

    state.tabId = generated
    return generated
  }

  function dispatchSessionLost(reason, source = 'local') {
    const normalizedReason = normalizeAuthSyncReason(reason)
    window.dispatchEvent(new CustomEvent('poof:auth-session-lost', {
      detail: {
        reason: normalizedReason,
        source,
        ts: Date.now(),
      },
    }))
  }

  function scheduleAuthReload() {
    if (state.shouldReload) return
    state.shouldReload = true

    window.setTimeout(() => {
      try {
        window.location.reload()
      } catch (_) {}
    }, 50)
  }

  function handleIncomingSignal(message = null) {
    if (!message || message.type !== 'poof-auth-session-sync') return
    if (message.tabId === ensureTabId()) return

    const now = Date.now()
    if (now - Number(state.lastHandledTs || 0) < AUTH_SYNC_RELOAD_DEBOUNCE_MS) return
    state.lastHandledTs = now

    const reason = normalizeAuthSyncReason(message.reason, 'cross_tab_session_loss')
    dispatchSessionLost(reason, 'cross-tab')
    scheduleAuthReload()
  }

  function emitSessionLoss(reason, source = 'local') {
    const normalizedReason = normalizeAuthSyncReason(reason)
    const envelope = {
      type: 'poof-auth-session-sync',
      tabId: ensureTabId(),
      emittedAt: Date.now(),
      reason: normalizedReason,
      source,
    }

    if (state.channel) {
      try {
        state.channel.postMessage(envelope)
      } catch (_) {}
    }

    if (canUseStorage()) {
      try {
        window.localStorage.setItem(AUTH_SYNC_STORAGE_KEY, JSON.stringify(envelope))
        window.localStorage.removeItem(AUTH_SYNC_STORAGE_KEY)
      } catch (_) {}
    }
  }

  ensureTabId()

  if (canUseBroadcastChannel()) {
    try {
      state.channel = new BroadcastChannel(AUTH_SYNC_CHANNEL)
      state.channel.onmessage = (event) => handleIncomingSignal(event?.data || null)
    } catch (_) {
      state.channel = null
    }
  }

  window.addEventListener('storage', (event) => {
    if (event.key !== AUTH_SYNC_STORAGE_KEY || !event.newValue) return

    try {
      const message = JSON.parse(event.newValue)
      handleIncomingSignal(message)
    } catch (_) {}
  })

  document.addEventListener('submit', (event) => {
    const form = event.target
    if (!(form instanceof HTMLFormElement)) return
    if (!/\/logout\/?$/.test(String(form.action || ''))) return

    emitSessionLoss('logout_submitted', 'logout_form')
  }, true)

  if (window.axios?.interceptors?.response) {
    window.axios.interceptors.response.use(
      (response) => response,
      (error) => {
        const status = Number(error?.response?.status)
        if (isSessionLossStatus(status)) {
          const reason = status === 419 ? 'session_expired_http_419' : 'unauthorized_http_401'
          dispatchSessionLost(reason, 'axios')
          emitSessionLoss(reason, 'axios')
        }

        return Promise.reject(error)
      },
    )
  }

  if (typeof window.fetch === 'function') {
    const nativeFetch = window.fetch.bind(window)

    window.fetch = async (...args) => {
      const response = await nativeFetch(...args)
      if (isSessionLossStatus(response.status)) {
        const reason = response.status === 419 ? 'session_expired_http_419' : 'unauthorized_http_401'
        dispatchSessionLost(reason, 'fetch')
        emitSessionLoss(reason, 'fetch')
      }
      return response
    }
  }
}

export {
  isSessionLossStatus,
  normalizeAuthSyncReason,
}
