import './bootstrap'
import Alpine from 'alpinejs'
import poofTimeCarousel from './poof/carousel'
import addressAutocomplete from './address-autocomplete'
import {
  POOF_BOOT_FLAGS,
  registerSharedAlpineComponents,
  shouldBootStandaloneAlpine,
  shouldStartLivewireRuntime,
  shouldStartStandaloneAlpine,
} from './poof/runtime-bootstrap'

const sharedAlpineComponents = {
  poofTimeCarousel,
  addressAutocomplete,
}

export function registerAlpineComponents(instance) {
  return registerSharedAlpineComponents(instance, sharedAlpineComponents)
}

export function bootReactiveRuntime() {
  const livewire = window.Livewire ?? null
  const alpine = window.Alpine ?? null

  if (livewire && alpine) {
    registerAlpineComponents(alpine)

    if (shouldStartLivewireRuntime({ livewire, alpine, globals: window })) {
      livewire.start()
      window[POOF_BOOT_FLAGS.livewireStarted] = true
    }

    return
  }

  const hasLivewireConfig = Boolean(window.livewireScriptConfig)

  // Standalone Alpine pages (without Livewire runtime config)
  if (shouldBootStandaloneAlpine({ hasLivewireConfig })) {
    window.Alpine = alpine || Alpine
    registerAlpineComponents(window.Alpine)

    if (shouldStartStandaloneAlpine({ alpine: window.Alpine, globals: window })) {
      window.Alpine.start()
      window[POOF_BOOT_FLAGS.alpineStarted] = true
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
