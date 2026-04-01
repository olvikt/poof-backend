import './bootstrap'
import Alpine from 'alpinejs'
import poofTimeCarousel from './poof/carousel'
import addressAutocomplete from './address-autocomplete'
import {
  beginRuntimeBoot,
  endRuntimeBoot,
  emitUiRuntimeMarker,
  evaluateLivewireRuntimeBoot,
  evaluateStandaloneAlpineBoot,
  lockRuntimeMode,
  POOF_BOOT_FLAGS,
  POOF_RUNTIME_MODE,
  registerSharedAlpineComponents,
  shouldBootStandaloneAlpine,
} from './poof/runtime-bootstrap'

const sharedAlpineComponents = {
  poofTimeCarousel,
  addressAutocomplete,
}

let livewireRuntimePromise = null
let runtimeBootPromise = null

async function loadLivewireRuntime() {
  if (!livewireRuntimePromise) {
    livewireRuntimePromise = import('../../vendor/livewire/livewire/dist/livewire.esm')
  }

  return livewireRuntimePromise
}

export function registerAlpineComponents(instance) {
  return registerSharedAlpineComponents(instance, sharedAlpineComponents)
}

export async function bootReactiveRuntime() {
  if (runtimeBootPromise) {
    return runtimeBootPromise
  }

  runtimeBootPromise = (async () => {
  const runtimeDiagnostics = String(import.meta?.env?.VITE_MAP_RUNTIME_DIAGNOSTICS || '').toLowerCase() === 'true'
  emitUiRuntimeMarker('ui_runtime_bootstrap_started', {
    source: 'resources/js/app.js',
    hasLivewire: Boolean(window.Livewire),
    hasAlpine: Boolean(window.Alpine),
  }, { globals: window, diagnostics: runtimeDiagnostics })

  const runtimeBoot = beginRuntimeBoot({ globals: window })
  if (!runtimeBoot.allowed) {
    emitUiRuntimeMarker('ui_runtime_bootstrap_skipped', {
      source: 'resources/js/app.js',
      reason: runtimeBoot.reason,
    }, { globals: window, diagnostics: runtimeDiagnostics })
    return
  }

  try {
  const hasLivewireConfig = Boolean(window.livewireScriptConfig)

  if (!hasLivewireConfig && shouldBootStandaloneAlpine({ hasLivewireConfig })) {
    const runtimeMode = lockRuntimeMode(POOF_RUNTIME_MODE.standalone, { globals: window })
    if (!runtimeMode.allowed) {
      emitUiRuntimeMarker('ui_runtime_bootstrap_skipped', {
        source: 'standalone_alpine',
        reason: runtimeMode.reason,
      }, { globals: window, diagnostics: runtimeDiagnostics })
      return
    }

    window.Alpine = window.Alpine ?? Alpine
    registerAlpineComponents(window.Alpine)

    const standaloneBoot = evaluateStandaloneAlpineBoot({ alpine: window.Alpine, globals: window })
    if (standaloneBoot.allowed) {
      window[POOF_BOOT_FLAGS.alpineStarting] = true
      try {
        window.Alpine.start()
        window[POOF_BOOT_FLAGS.alpineStarted] = true
      } finally {
        window[POOF_BOOT_FLAGS.alpineStarting] = false
      }

      emitUiRuntimeMarker('ui_runtime_bootstrap_alpine_started', {
        source: 'standalone_alpine',
      }, { globals: window, diagnostics: runtimeDiagnostics })
    } else if (standaloneBoot.reason === 'duplicate_guarded') {
      emitUiRuntimeMarker('ui_runtime_bootstrap_skipped', {
        source: 'standalone_alpine',
        reason: standaloneBoot.reason,
      }, { globals: window, diagnostics: runtimeDiagnostics })
    }

    return
  }

  const runtimeMode = lockRuntimeMode(POOF_RUNTIME_MODE.livewire, { globals: window })
  if (!runtimeMode.allowed) {
    emitUiRuntimeMarker('ui_runtime_bootstrap_skipped', {
      source: 'livewire',
      reason: runtimeMode.reason,
    }, { globals: window, diagnostics: runtimeDiagnostics })
    return
  }

  let livewire = window.Livewire ?? null
  let alpine = window.Alpine ?? null

  if (!livewire || !alpine) {
    const livewireRuntime = await loadLivewireRuntime()
    livewire = livewire ?? livewireRuntime?.Livewire ?? null
    alpine = alpine ?? livewireRuntime?.Alpine ?? null
  }

  if (!livewire || !alpine) {
    emitUiRuntimeMarker('ui_runtime_bootstrap_skipped', {
      source: 'livewire',
      reason: 'missing_runtime_dependencies',
      level: 'warn',
    }, { globals: window, diagnostics: runtimeDiagnostics })
    return
  }

  window.Livewire = livewire
  window.Alpine = alpine
  registerAlpineComponents(alpine)

  const livewireBoot = evaluateLivewireRuntimeBoot({ livewire, alpine, globals: window })
  if (livewireBoot.allowed) {
    window[POOF_BOOT_FLAGS.livewireStarting] = true
    try {
      livewire.start()
      window[POOF_BOOT_FLAGS.livewireStarted] = true
    } finally {
      window[POOF_BOOT_FLAGS.livewireStarting] = false
    }

    emitUiRuntimeMarker('ui_runtime_bootstrap_livewire_started', {
      source: 'livewire',
    }, { globals: window, diagnostics: runtimeDiagnostics })
  } else if (livewireBoot.reason === 'duplicate_guarded') {
    emitUiRuntimeMarker('ui_runtime_bootstrap_skipped', {
      source: 'livewire',
      reason: livewireBoot.reason,
      }, { globals: window, diagnostics: runtimeDiagnostics })
  }
  } finally {
    endRuntimeBoot({ globals: window })
  }
  })()

  try {
    return await runtimeBootPromise
  } finally {
    runtimeBootPromise = null
  }
}

void bootReactiveRuntime()
document.addEventListener('livewire:init', () => {
  void bootReactiveRuntime()
}, { once: true })

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
