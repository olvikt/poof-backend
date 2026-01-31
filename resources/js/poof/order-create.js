import initCarousel from './carousel'
import initMap from './map'

/**
 * ============================================================
 * POOF — Order Create bootstrap
 * ============================================================
 *
 * ✅ Без глобальных флагов
 * ✅ Работает с Livewire v3 + Alpine
 * ✅ Карта безопасно переживает date picker / rerender
 * ✅ Идемпотентная инициализация
 */

(function () {
  // ✅ флаг в замыкании (не global)
  let livewireSyncBound = false

  /**
   * Проверяем, что мы реально на экране создания заказа
   */
  function isOrderCreatePage() {
    return !!document.getElementById('order-create-root')
  }

  /**
   * ------------------------------------------------------------
   * Livewire → Map sync (bind once)
   * ------------------------------------------------------------
   * Когда в PHP меняются lat/lng (например selectAddress),
   * карта сама подтягивает и ставит маркер.
   */
  function bindLivewireToMapSyncOnce() {
    if (livewireSyncBound) return
    livewireSyncBound = true

    const attach = () => {
      if (!window.Livewire?.hook) return

      Livewire.hook('message.processed', (_, component) => {
        if (!isOrderCreatePage()) return

        // Берём именно OrderCreate (корень компонента)
        const root = document.getElementById('order-create-root')
        if (!root) return
        if (component.el !== root) return

        const lat = component.get?.('lat')
        const lng = component.get?.('lng')

        if (lat != null && lng != null) {
          // ✅ тихо ставим маркер (без emit обратно в Livewire)
          window.POOF?.setMarkerSilent?.(lat, lng, 18)
        }
      })
    }

    // Если Livewire уже инициализирован — вешаем сразу
    if (window.Livewire?.hook) {
      attach()
      return
    }

    // Иначе дождёмся livewire:init
    document.addEventListener('livewire:init', attach, { once: true })
  }

  /**
   * Главная инициализация
   */
  function boot() {
    if (!isOrderCreatePage()) return

    window.POOF = window.POOF || {}
    const POOF = window.POOF

    // 🔁 инициализация UI (безопасно)
    initCarousel()
    initMap()

    // ✅ связываем изменения lat/lng в Livewire с картой
    bindLivewireToMapSyncOnce()

    /**
     * ------------------------------------------------------------
     * Date Picker (global access)
     * ------------------------------------------------------------
     * Используется Alpine / кнопкой "Інша дата"
     */
    function openDatePicker() {
      const input = document.createElement('input')
      input.type = 'date'
      input.style.position = 'fixed'
      input.style.opacity = '0'
      input.style.pointerEvents = 'none'

      document.body.appendChild(input)

      input.addEventListener(
        'change',
        () => {
          if (!input.value) return

          try {
            const wireRoot = document.querySelector('[wire\\:id]')
            const cmp =
              wireRoot &&
              window.Livewire?.find(wireRoot.getAttribute('wire:id'))

            // обновляем дату в Livewire
            cmp?.set('scheduled_date', input.value)

            // 🔑 ВАЖНО: после выбора даты мягко переинициализируем карту
            // (фикс мобильных WebView / Safari)
            window.dispatchEvent(new Event('map:init'))
          } catch (_) {}

          document.body.removeChild(input)
        },
        { once: true }
      )

      // Safari / Chrome
      if (input.showPicker) input.showPicker()
      else input.click()
    }

    // экспортируем глобально (как у тебя было)
    POOF.openDatePicker = openDatePicker
    window.openDatePicker = openDatePicker
  }

  /**
   * Первый запуск
   */
  document.addEventListener('DOMContentLoaded', boot)

  /**
   * Повторный запуск при навигации Livewire
   * (initMap и initCarousel внутри безопасны)
   */
  document.addEventListener('livewire:navigated', boot)
})()
