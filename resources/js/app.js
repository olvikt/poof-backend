import './bootstrap'
import Alpine from 'alpinejs'
import poofTimeCarousel from './poof/carousel'
import addressAutocomplete from './address-autocomplete'
import {
  emitUiRuntimeMarker,
  evaluateLivewireRuntimeBoot,
  evaluateStandaloneAlpineBoot,
  POOF_BOOT_FLAGS,
  registerSharedAlpineComponents,
  shouldBootStandaloneAlpine,
} from './poof/runtime-bootstrap'

const sharedAlpineComponents = {
  poofTimeCarousel,
  addressAutocomplete,
}

export function registerAlpineComponents(instance) {
  return registerSharedAlpineComponents(instance, sharedAlpineComponents)
}

export function bootReactiveRuntime() {
  const runtimeDiagnostics = String(import.meta?.env?.VITE_MAP_RUNTIME_DIAGNOSTICS || '').toLowerCase() === 'true'
  emitUiRuntimeMarker('ui_runtime_bootstrap_started', {
    source: 'resources/js/app.js',
    hasLivewire: Boolean(window.Livewire),
    hasAlpine: Boolean(window.Alpine),
  }, { globals: window, diagnostics: runtimeDiagnostics })

  const livewire = window.Livewire ?? null
  const alpine = window.Alpine ?? null

  if (livewire && alpine) {
    registerAlpineComponents(alpine)
    const livewireBoot = evaluateLivewireRuntimeBoot({ livewire, alpine, globals: window })
    if (livewireBoot.allowed) {
      livewire.start()
      window[POOF_BOOT_FLAGS.livewireStarted] = true
      emitUiRuntimeMarker('ui_runtime_bootstrap_livewire_started', {
        source: 'livewire',
      }, { globals: window, diagnostics: runtimeDiagnostics })
    } else if (livewireBoot.reason === 'duplicate_guarded') {
      emitUiRuntimeMarker('ui_runtime_bootstrap_skipped', {
        source: 'livewire',
        reason: livewireBoot.reason,
      }, { globals: window, diagnostics: runtimeDiagnostics })
    }

    return
  }

  const hasLivewireConfig = Boolean(window.livewireScriptConfig)

  // Standalone Alpine pages (without Livewire runtime config)
  if (shouldBootStandaloneAlpine({ hasLivewireConfig })) {
    window.Alpine = alpine || Alpine
    registerAlpineComponents(window.Alpine)
    const standaloneBoot = evaluateStandaloneAlpineBoot({ alpine: window.Alpine, globals: window })
    if (standaloneBoot.allowed) {
      window.Alpine.start()
      window[POOF_BOOT_FLAGS.alpineStarted] = true
      emitUiRuntimeMarker('ui_runtime_bootstrap_alpine_started', {
        source: 'standalone_alpine',
      }, { globals: window, diagnostics: runtimeDiagnostics })
    } else if (standaloneBoot.reason === 'duplicate_guarded') {
      emitUiRuntimeMarker('ui_runtime_bootstrap_skipped', {
        source: 'standalone_alpine',
        reason: standaloneBoot.reason,
      }, { globals: window, diagnostics: runtimeDiagnostics })
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
