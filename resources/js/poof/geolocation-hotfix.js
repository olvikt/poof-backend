export function normalizeRuntimeOnlineState(payload = null, fallback = null) {
  if (payload && typeof payload === 'object') {
    if (typeof payload.online === 'boolean') return payload.online
    if (payload.snapshot && typeof payload.snapshot.online === 'boolean') return payload.snapshot.online
  }

  return typeof fallback === 'boolean' ? fallback : null
}

export function shouldStartCourierTracker({
  isCourier = false,
  online = false,
  watchId = null,
  geolocationSupported = true,
} = {}) {
  return Boolean(isCourier && online && watchId === null && geolocationSupported)
}

export function isValidGeolocationPayload(lat, lng, accuracy = null, maxAccuracyMeters = 120) {
  const latN = Number(lat)
  const lngN = Number(lng)
  const accuracyN = Number(accuracy)

  const coordsValid = Number.isFinite(latN)
    && Number.isFinite(lngN)
    && Math.abs(latN) <= 90
    && Math.abs(lngN) <= 180
    && !(latN === 0 && lngN === 0)

  if (!coordsValid) return { coordsValid: false, courierConfirmed: false }

  const accuracyValid = !Number.isFinite(accuracyN) || accuracyN <= maxAccuracyMeters

  return {
    coordsValid: true,
    courierConfirmed: accuracyValid,
  }
}

export function shouldShowDefaultCityUnconfirmedState({
  hasOrder = false,
  hasCourierCoords = false,
  courierConfirmed = false,
} = {}) {
  if (hasOrder) return false
  if (!hasCourierCoords) return true

  return !courierConfirmed
}

export function buildDeniedGeolocationUiState({
  source = 'unknown',
  message = 'Не вдалося отримати доступ до геолокації. Дозвольте доступ у налаштуваннях браузера/застосунку або виберіть адресу вручну.',
} = {}) {
  return {
    status: 'error',
    message: String(message || '').trim() || 'Не вдалося отримати доступ до геолокації.',
    source,
    reason: 'permission_denied',
  }
}
