import test from 'node:test'
import assert from 'node:assert/strict'

import {
  normalizeRuntimeOnlineState,
  shouldShowDefaultCityUnconfirmedState,
  shouldStartCourierTracker,
  isValidGeolocationPayload,
} from '../../../resources/js/poof/geolocation-hotfix.js'

test('courier tracker starts when entering cabinet in online state', () => {
  const result = shouldStartCourierTracker({
    isCourier: true,
    online: true,
    watchId: null,
    geolocationSupported: true,
  })

  assert.equal(result, true)
})

test('runtime bootstrap delay can recover online state from runtime-sync payload', () => {
  const result = normalizeRuntimeOnlineState({
    snapshot: {
      online: true,
    },
  }, false)

  assert.equal(result, true)
})

test('denied geolocation is not treated as confirmed payload', () => {
  const result = isValidGeolocationPayload(48.4671, 35.0382, 450)

  assert.deepEqual(result, {
    coordsValid: true,
    courierConfirmed: false,
  })
})

test('map does not pretend confirmed location when only default city exists', () => {
  const shouldShowWarning = shouldShowDefaultCityUnconfirmedState({
    hasOrder: false,
    hasCourierCoords: false,
    courierConfirmed: false,
  })

  assert.equal(shouldShowWarning, true)
})

test('malformed geolocation payload is rejected and cannot look like success', () => {
  const malformed = isValidGeolocationPayload('bad', null, null)

  assert.deepEqual(malformed, {
    coordsValid: false,
    courierConfirmed: false,
  })
})

test('already started watch does not re-trigger geolocation acquisition', () => {
  const result = shouldStartCourierTracker({
    isCourier: true,
    online: true,
    watchId: 10,
    geolocationSupported: true,
  })

  assert.equal(result, false)
})
