import initCarousel from './carousel.js'
import initMap from './map.js'

export function dispatchGeocodeDebounced({ component = null, token, livewire = null } = {}) {
  if (!token) return false

  if (component && typeof component.call === 'function') {
    try {
      component.call('runDebouncedGeocode', token)
      return true
    } catch (_) {}
  }

  if (livewire && typeof livewire.dispatch === 'function') {
    try {
      livewire.dispatch('geocode:debounced', { token })
      return true
    } catch (_) {}
  }

  return false
}

/**
 * ============================================================
 * POOF — Order Create bootstrap
 * ============================================================
 */
if (typeof window !== 'undefined' && typeof document !== 'undefined') {
;(function () {
  const DEBUG_MAP = String(import.meta?.env?.VITE_MAP_DEBUG || '').toLowerCase() === 'true'
  const params = new URLSearchParams(window.location.search)
  const addressId = params.get('address_id')

  if (addressId) {
    window.POOF = window.POOF || {}

    window.POOF.addressState = {
      source: 'saved',
      locked: true,
    }

    if (DEBUG_MAP) {
      console.debug('[POOF] saved address detected → geolocation disabled')
    }
  }

  window.POOF = window.POOF || {}

  let livewireMapSyncBound = false
  let geocodeDebounceBound = false
  let datePickerBound = false
  let geocodeTimer = null

  function isOrderCreatePage() {
    return !!document.getElementById('order-create-root')
  }

  function getOrderCreateRoot() {
    return document.getElementById('order-create-root')
  }

  function getOrderCreateComponent() {
    const root = getOrderCreateRoot()
    if (!root) return null

    const wireEl = root.closest('[wire\\:id]') || root.querySelector('[wire\\:id]')
    const wireId = wireEl?.getAttribute?.('wire:id')
    if (!wireId) return null

    try {
      return window.Livewire?.find?.(wireId) || null
    } catch (_) {
      return null
    }
  }

  function bindLivewireToMapSyncOnce() {
    if (livewireMapSyncBound) return
    livewireMapSyncBound = true

    const attach = () => {
      if (!window.Livewire?.hook) return

      window.Livewire.hook('message.processed', (_, component) => {
        if (!isOrderCreatePage()) return

        const root = getOrderCreateRoot()
        if (!root) return
        if (component?.el !== root) return

        const lat = component.get?.('lat')
        const lng = component.get?.('lng')

        if (lat != null && lng != null) {
          window.POOF?.setMarkerSilent?.(lat, lng, 18)
        }
      })
    }

    if (window.Livewire?.hook) {
      attach()
      return
    }

    document.addEventListener('livewire:init', attach, { once: true })
  }

  function bindGeocodeDebounceOnce() {
    if (geocodeDebounceBound) return
    geocodeDebounceBound = true

    window.addEventListener('geocode:schedule', (e) => {
      if (!isOrderCreatePage()) return

      const token = e?.detail?.token
      if (!token) return

      clearTimeout(geocodeTimer)
      geocodeTimer = setTimeout(() => {
        dispatchGeocodeDebounced({
          component: getOrderCreateComponent(),
          token,
          livewire: window.Livewire,
        })
      }, 600)
    })
  }

  function bindDatePickerOnce() {
    if (datePickerBound) return
    datePickerBound = true

    function openDatePicker() {
      if (!isOrderCreatePage()) return

      const input = document.createElement('input')
      input.type = 'date'
      input.style.position = 'fixed'
      input.style.opacity = '0'
      input.style.pointerEvents = 'none'
      input.style.left = '-9999px'
      input.style.top = '-9999px'

      document.body.appendChild(input)

      const cleanup = () => {
        try {
          document.body.removeChild(input)
        } catch (_) {}
      }

      input.addEventListener(
        'change',
        () => {
          const value = input.value
          if (!value) {
            cleanup()
            return
          }

          try {
            const cmp = getOrderCreateComponent()
            cmp?.set?.('scheduled_date', value)
            window.dispatchEvent(new Event('map:init'))
          } catch (_) {}

          cleanup()
        },
        { once: true }
      )

      try {
        if (input.showPicker) input.showPicker()
        else input.click()
      } catch (_) {
        input.click()
      }
    }

    window.POOF = window.POOF || {}
    window.POOF.openDatePicker = openDatePicker
    window.openDatePicker = openDatePicker
  }

  function boot() {
    if (!isOrderCreatePage()) return

    window.POOF = window.POOF || {}

    initCarousel()
    initMap()

    setTimeout(() => {
      const latInput = document.querySelector('input[name="lat"]')
      const lngInput = document.querySelector('input[name="lng"]')

      if (!latInput || !lngInput) {
        return
      }

      const lat = parseFloat(latInput.value)
      const lng = parseFloat(lngInput.value)

      if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return
      }

      if (DEBUG_MAP) {
        console.debug('[POOF] center map from form coordinates', lat, lng)
      }

      if (window.POOF?.map && window.POOF?.setMarker) {
        window.POOF.setMarker(lat, lng)
        window.POOF.map.setView([lat, lng], 17)
      }
    }, 200)

    bindLivewireToMapSyncOnce()
    bindGeocodeDebounceOnce()
    bindDatePickerOnce()
  }

  document.addEventListener('DOMContentLoaded', boot)
  document.addEventListener('livewire:navigated', boot)
})()
}
