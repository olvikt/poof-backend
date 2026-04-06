import test from 'node:test'
import assert from 'node:assert/strict'

import { resolveCourierMarkerLifecycle } from '../../../resources/js/poof/map.js'

function applyCourierRuntimeUpdate(state, payload) {
  const lifecycle = resolveCourierMarkerLifecycle({
    isAddressPickerFlow: state.isAddressPickerFlow,
    hasCourier: Boolean(payload.hasCourier),
    hasOrder: Boolean(payload.hasOrder),
  })

  if (lifecycle.shouldClearFloatingMarker) {
    state.hasFloatingMarker = false
  }

  state.hasCourierMarker = lifecycle.shouldRenderCourierMarker
  state.hasOrderMarker = lifecycle.shouldRenderOrderMarker
  state.hasRadiusCircle = lifecycle.shouldRenderRadiusCircle

  return lifecycle
}

function teardown(state) {
  state.hasFloatingMarker = false
  state.hasCourierMarker = false
  state.hasOrderMarker = false
  state.hasRadiusCircle = false
}

test('no active order => floating marker is cleared and only courier marker remains', () => {
  const state = {
    isAddressPickerFlow: false,
    hasFloatingMarker: true,
    hasCourierMarker: false,
    hasOrderMarker: false,
    hasRadiusCircle: false,
  }

  const lifecycle = applyCourierRuntimeUpdate(state, { hasCourier: true, hasOrder: false })

  assert.equal(lifecycle.shouldClearFloatingMarker, true)
  assert.equal(state.hasFloatingMarker, false)
  assert.equal(state.hasCourierMarker, true)
  assert.equal(state.hasOrderMarker, false)
})

test('active order => courier and order overlays are both rendered and stay distinct', () => {
  const lifecycle = resolveCourierMarkerLifecycle({
    isAddressPickerFlow: false,
    hasCourier: true,
    hasOrder: true,
  })

  assert.equal(lifecycle.shouldRenderCourierMarker, true)
  assert.equal(lifecycle.shouldRenderOrderMarker, true)
  assert.equal(lifecycle.shouldRenderRadiusCircle, true)
  assert.equal(lifecycle.shouldClearFloatingMarker, true)
})

test('remount/navigation runtime update does not recreate floating duplicate', () => {
  const state = {
    isAddressPickerFlow: false,
    hasFloatingMarker: true,
    hasCourierMarker: false,
    hasOrderMarker: false,
    hasRadiusCircle: false,
  }

  applyCourierRuntimeUpdate(state, { hasCourier: true, hasOrder: false })
  // emulate remount + next runtime update on the same singleton state
  applyCourierRuntimeUpdate(state, { hasCourier: true, hasOrder: false })

  assert.equal(state.hasFloatingMarker, false)
  assert.equal(state.hasCourierMarker, true)
  assert.equal(state.hasOrderMarker, false)
})

test('watchPosition courier updates keep single courier visual path', () => {
  const state = {
    isAddressPickerFlow: false,
    hasFloatingMarker: true,
    hasCourierMarker: false,
    hasOrderMarker: false,
    hasRadiusCircle: false,
  }

  applyCourierRuntimeUpdate(state, { hasCourier: true, hasOrder: false })
  applyCourierRuntimeUpdate(state, { hasCourier: true, hasOrder: false })
  applyCourierRuntimeUpdate(state, { hasCourier: true, hasOrder: false })

  assert.equal(state.hasFloatingMarker, false)
  assert.equal(state.hasCourierMarker, true)
  assert.equal(state.hasOrderMarker, false)
  assert.equal(state.hasRadiusCircle, true)
})

test('bootstrap followed by runtime updates does not leave stale floating marker', () => {
  const state = {
    isAddressPickerFlow: false,
    hasFloatingMarker: true,
    hasCourierMarker: false,
    hasOrderMarker: false,
    hasRadiusCircle: false,
  }

  // bootstrap payload
  applyCourierRuntimeUpdate(state, { hasCourier: true, hasOrder: false })
  // later runtime sync payload
  applyCourierRuntimeUpdate(state, { hasCourier: true, hasOrder: true })

  assert.equal(state.hasFloatingMarker, false)
  assert.equal(state.hasCourierMarker, true)
  assert.equal(state.hasOrderMarker, true)
})

test('teardown fully clears marker state after previous courier runtime', () => {
  const state = {
    isAddressPickerFlow: false,
    hasFloatingMarker: true,
    hasCourierMarker: true,
    hasOrderMarker: true,
    hasRadiusCircle: true,
  }

  teardown(state)

  assert.equal(state.hasFloatingMarker, false)
  assert.equal(state.hasCourierMarker, false)
  assert.equal(state.hasOrderMarker, false)
  assert.equal(state.hasRadiusCircle, false)
})
