import initCarousel from './carousel'
import initMap from './map'

/**
 * ============================================================
 * POOF — Order Create bootstrap
 * ============================================================
 *
 * ✅ Без глобальных флагов (только замыкание)
 * ✅ Livewire v3 + Alpine friendly
 * ✅ Идемпотентно при livewire:navigated
 * ✅ Карта и UI безопасно переживают rerender
 * ✅ Debounce geocode через Browser Event → Livewire event
 */

;(function () {
  // ---------------------------------------------------------------------------
  // Private state (closure)
  // ---------------------------------------------------------------------------
  let livewireMapSyncBound = false
  let geocodeDebounceBound = false
  let datePickerBound = false

  // Debounce timer
  let geocodeTimer = null

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------
  function isOrderCreatePage() {
    return !!document.getElementById('order-create-root')
  }

  function getOrderCreateRoot() {
    return document.getElementById('order-create-root')
  }

  /**
   * Получить Livewire component instance именно для OrderCreate
   * Надёжнее, чем querySelector('[wire:id]') по всей странице
   */
  function getOrderCreateComponent() {
    const root = getOrderCreateRoot()
    if (!root) return null

    // wire:id может быть на самом root или на ближайшем родителе
    const wireEl = root.closest('[wire\\:id]') || root.querySelector('[wire\\:id]')
    const wireId = wireEl?.getAttribute?.('wire:id')
    if (!wireId) return null

    try {
      return window.Livewire?.find?.(wireId) || null
    } catch (_) {
      return null
    }
  }

  // ---------------------------------------------------------------------------
  // Livewire → Map sync (bind once)
  // ---------------------------------------------------------------------------
  function bindLivewireToMapSyncOnce() {
    if (livewireMapSyncBound) return
    livewireMapSyncBound = true

    const attach = () => {
      if (!window.Livewire?.hook) return

      // После обработки сообщения Livewire — читаем новые lat/lng и ставим маркер
      window.Livewire.hook('message.processed', (_, component) => {
        if (!isOrderCreatePage()) return

        const root = getOrderCreateRoot()
        if (!root) return

        // Ограничиваемся именно корневым компонентом заказа:
        // component.el должен совпасть с root, иначе можем поймать чужие компоненты.
        if (component?.el !== root) return

        const lat = component.get?.('lat')
        const lng = component.get?.('lng')

        if (lat != null && lng != null) {
          // ✅ тихо ставим маркер (без emit обратно в Livewire)
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

  // ---------------------------------------------------------------------------
  // Geocode debounce (bind once)
  // ---------------------------------------------------------------------------
  /**
   * PHP: dispatch('geocode:schedule', token: '...')
   * JS: ждём 600ms и отправляем обратно:
   *     Livewire.dispatch('geocode:debounced', { token })
   */
  function bindGeocodeDebounceOnce() {
    if (geocodeDebounceBound) return
    geocodeDebounceBound = true

    window.addEventListener('geocode:schedule', (e) => {
      if (!isOrderCreatePage()) return

      const token = e?.detail?.token
      if (!token) return

      clearTimeout(geocodeTimer)
      geocodeTimer = setTimeout(() => {
        try {
          window.Livewire?.dispatch?.('geocode:debounced', { token })
        } catch (_) {}
      }, 600)
    })
  }

  // ---------------------------------------------------------------------------
  // Date Picker (bind once + exported)
  // ---------------------------------------------------------------------------
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
            // ✅ обновляем дату в Livewire
            cmp?.set?.('scheduled_date', value)

            // 🔑 мягко реинициализируем карту (фикс Safari/WebView)
            window.dispatchEvent(new Event('map:init'))
          } catch (_) {}

          cleanup()
        },
        { once: true }
      )

      // Safari / Chrome
      try {
        if (input.showPicker) input.showPicker()
        else input.click()
      } catch (_) {
        // fallback
        input.click()
      }
    }

    // экспортируем (как у тебя было)
    window.POOF = window.POOF || {}
    window.POOF.openDatePicker = openDatePicker
    window.openDatePicker = openDatePicker
  }

  // ---------------------------------------------------------------------------
  // Boot
  // ---------------------------------------------------------------------------
  function boot() {
    if (!isOrderCreatePage()) return

    // namespace
    window.POOF = window.POOF || {}

    // UI init (должны быть идемпотентными внутри)
    initCarousel()
    initMap()

    // Sync & helpers
    bindLivewireToMapSyncOnce()
    bindGeocodeDebounceOnce()
    bindDatePickerOnce()
  }

  // First run
  document.addEventListener('DOMContentLoaded', boot)

  // Livewire navigation re-run (safe)
  document.addEventListener('livewire:navigated', boot)
})()
