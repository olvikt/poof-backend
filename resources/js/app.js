import './bootstrap'
import Alpine from 'alpinejs'
import poofTimeCarousel from './poof/carousel'
import addressAutocomplete from './address-autocomplete'

function registerAlpineComponents(instance) {
  if (!instance || instance.__poofComponentsRegistered) return

  instance.data('poofTimeCarousel', poofTimeCarousel)
  instance.data('addressAutocomplete', addressAutocomplete)
  instance.__poofComponentsRegistered = true
}

function bootReactiveRuntime() {
  const livewire = window.Livewire ?? null
  const alpine = window.Alpine ?? null

  if (livewire && alpine) {
    registerAlpineComponents(alpine)

    if (!window.__poofLivewireStarted && typeof livewire.start === 'function') {
      livewire.start()
      window.__poofLivewireStarted = true
    }

    return
  }

  const hasLivewireConfig = Boolean(window.livewireScriptConfig)

  // Standalone Alpine pages (without Livewire runtime config)
  if (!hasLivewireConfig) {
    window.Alpine = alpine || Alpine
    registerAlpineComponents(window.Alpine)

    if (!window.__poofAlpineStarted) {
      window.Alpine.start()
      window.__poofAlpineStarted = true
    }
  }
}

bootReactiveRuntime()
document.addEventListener('livewire:init', bootReactiveRuntime)

// CSS
import '../css/app.css'

// UI
import './poof/bottom-sheet'

// MAP
import initMap from './poof/map'

// OrderCreate
import './poof/order-create'
import initAuthSessionSync from './poof/auth-session-sync'

initMap()
initAuthSessionSync()

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
  })
}
