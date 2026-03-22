import test from 'node:test'
import assert from 'node:assert/strict'
import addressAutocomplete from '../../resources/js/address-autocomplete.js'

function makeComponent(overrides = {}) {
  global.window = {
    POOF: {
      map: {
        preferredVisibleAddressPoint: null,
      },
    },
    dispatchEvent() {},
  }

  const component = addressAutocomplete()
  Object.assign(component, overrides)
  return component
}

test('currentMapPointSelection uses visible-address coordinates instead of stale lat/lng', () => {
  const component = makeComponent({
    search: 'Мандриківська 173, Dnipro',
    summarySearch: 'Мандриківська 173, Dnipro',
    street: 'Мандриківська',
    house: '173',
    city: 'Dnipro',
    region: 'Dnipropetrovsk region',
    lat: 48.4240053,
    lng: 35.0588747,
    visibleAddressSelectionPoint: {
      lat: 48.4671,
      lng: 35.0382,
      label: 'Мандриківська 173, Dnipro',
      reason: 'resolved-address',
      updatedAt: Date.now(),
    },
  })

  const item = component.currentMapPointSelection()

  assert.ok(item)
  assert.equal(item.label, 'Мандриківська 173, Dnipro')
  assert.equal(item.line1, 'Мандриківська 173, Dnipro')
  assert.equal(item.lat, 48.4671)
  assert.equal(item.lng, 35.0382)
})

test('currentMapPointSelection returns null when only stale fallback coords remain behind visible text', () => {
  const component = makeComponent({
    search: 'Мандриківська 173, Dnipro',
    summarySearch: 'Мандриківська 173, Dnipro',
    street: 'Мандриківська',
    house: '173',
    city: 'Dnipro',
    region: 'Dnipropetrovsk region',
    lat: 48.4240053,
    lng: 35.0588747,
    visibleAddressSelectionPoint: {
      lat: 48.4671,
      lng: 35.0382,
      label: 'Мандриківська 171, Dnipro',
      reason: 'resolved-address',
      updatedAt: Date.now(),
    },
  })

  assert.equal(component.currentMapPointSelection(), null)
})
