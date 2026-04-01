import './bootstrap'
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

let livewireRuntimePromise = null
let standaloneAlpinePromise = null

async function loadLivewireRuntime() {
  if (!livewireRuntimePromise) {
    livewireRuntimePromise = import('../../vendor/livewire/livewire/dist/livewire.esm')
  }

  return livewireRuntimePromise
}

async function loadStandaloneAlpineRuntime() {
  if (!standaloneAlpinePromise) {
    standaloneAlpinePromise = import('alpinejs')
  }

  return standaloneAlpinePromise
}

export function registerAlpineComponents(instance) {
  return registerSharedAlpineComponents(instance, sharedAlpineComponents)
}

export async function bootReactiveRuntime() {
  const runtimeDiagnostics = String(import.meta?.env?.VITE_MAP_RUNTIME_DIAGNOSTICS || '').toLowerCase() === 'true'
  emitUiRuntimeMarker('ui_runtime_bootstrap_started', {
    source: 'resources/js/app.js',
    hasLivewire: Boolean(window.Livewire),
    hasAlpine: Boolean(window.Alpine),
  }, { globals: window, diagnostics: runtimeDiagnostics })

  const hasLivewireConfig = Boolean(window.livewireScriptConfig)
  let livewire = window.Livewire ?? null
  let alpine = window.Alpine ?? null

  if (!livewire && hasLivewireConfig) {
    const livewireRuntime = await loadLivewireRuntime()
    livewire = livewireRuntime?.Livewire ?? null
    alpine = alpine ?? livewireRuntime?.Alpine ?? null
  }

  if (livewire && alpine) {
    window.Livewire = livewire
    window.Alpine = alpine
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

  // Standalone Alpine pages (without Livewire runtime config)
  if (shouldBootStandaloneAlpine({ hasLivewireConfig })) {
    if (!alpine) {
      const standaloneAlpine = await loadStandaloneAlpineRuntime()
      alpine = standaloneAlpine?.default ?? standaloneAlpine?.Alpine ?? null
    }

    window.Alpine = alpine
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

void bootReactiveRuntime()
document.addEventListener('livewire:init', () => {
  void bootReactiveRuntime()
})

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
