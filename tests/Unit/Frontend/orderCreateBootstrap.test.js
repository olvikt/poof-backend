import test from 'node:test'
import assert from 'node:assert/strict'

import { dispatchGeocodeDebounced } from '../../../resources/js/poof/order-create.js'

test('geocode debounce prefers direct component action call', () => {
  const calls = []

  const component = {
    call(method, token) {
      calls.push({ method, token })
    },
  }

  const livewire = {
    dispatch() {
      throw new Error('must not fallback to global dispatch')
    },
  }

  const result = dispatchGeocodeDebounced({ component, token: 'geo_1', livewire })

  assert.equal(result, true)
  assert.deepEqual(calls, [{ method: 'runDebouncedGeocode', token: 'geo_1' }])
})

test('geocode debounce falls back to livewire.dispatch when component action is unavailable', () => {
  const emitted = []

  const livewire = {
    dispatch(event, payload) {
      emitted.push({ event, payload })
    },
  }

  const result = dispatchGeocodeDebounced({ component: null, token: 'geo_2', livewire })

  assert.equal(result, true)
  assert.deepEqual(emitted, [{ event: 'geocode:debounced', payload: { token: 'geo_2' } }])
})
