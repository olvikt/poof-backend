import test from 'node:test'
import assert from 'node:assert/strict'

import {
  buildCurrentLocationFallbackPlan,
  shouldApplyPersistedLocationOnBootstrap,
} from '../../../resources/js/poof/map.js'

test('address-picker bootstrap does not silently apply persisted location', () => {
  const result = shouldApplyPersistedLocationOnBootstrap({
    persistedLocation: { lat: 48.4671, lng: 35.0382, source: 'user' },
    bootstrapApplied: false,
    hasActiveOrderBootstrap: false,
    isAddressPickerFlow: true,
  })

  assert.equal(result, false)
})

test('non-address bootstrap can still reuse persisted location when no stronger source exists', () => {
  const result = shouldApplyPersistedLocationOnBootstrap({
    persistedLocation: { lat: 48.4671, lng: 35.0382, source: 'user' },
    bootstrapApplied: false,
    hasActiveOrderBootstrap: false,
    isAddressPickerFlow: false,
  })

  assert.equal(result, true)
})

test('current-location fallback stays explicit and does not close the address book', () => {
  const plan = buildCurrentLocationFallbackPlan({
    allowPersistedFallback: true,
    persistedLocation: { lat: 48.4671, lng: 35.0382, source: 'geolocation' },
    closeAddressBook: true,
    source: 'event',
    zoom: 18,
  })

  assert.deepEqual(plan, {
    lat: 48.4671,
    lng: 35.0382,
    source: 'event',
    persistSource: 'geolocation',
    zoom: 18,
    closeAddressBook: false,
    message: 'Точну геолокацію не вдалося отримати, тому показали останню збережену точку. За потреби посуньте мапу вручну.',
    warning: 'Точну локацію не вдалося отримати — використали останню збережену точку.',
    requestedCloseAddressBook: true,
    usedPersistedFallback: true,
  })
})

test('current-location fallback is skipped unless explicitly allowed', () => {
  const plan = buildCurrentLocationFallbackPlan({
    allowPersistedFallback: false,
    persistedLocation: { lat: 48.4671, lng: 35.0382, source: 'geolocation' },
    closeAddressBook: true,
  })

  assert.equal(plan, null)
})
