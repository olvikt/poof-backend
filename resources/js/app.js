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

async function bootReactiveRuntime() {
  let livewire = window.Livewire ?? null
  let alpine = window.Alpine ?? null

  if (!livewire) {
    const livewireCandidates = [
      '/vendor/livewire/livewire/dist/livewire.esm.js',
      '/vendor/livewire/livewire/dist/livewire.esm',
    ]

    for (const candidate of livewireCandidates) {
      try {
        const mod = await import(/* @vite-ignore */ candidate)
        if (mod?.Livewire && mod?.Alpine) {
          livewire = mod.Livewire
          alpine = mod.Alpine
          break
        }
      } catch (_) {
        // noop: try next candidate or Alpine-only fallback
      }
    }
  }

  if (livewire && alpine) {
    window.Livewire = livewire
    window.Alpine = alpine

    registerAlpineComponents(alpine)

    if (!window.__poofLivewireStarted) {
      livewire.start()
      window.__poofLivewireStarted = true
    }

    return
  }

  window.Alpine = alpine || Alpine
  registerAlpineComponents(window.Alpine)

  if (!window.__poofAlpineStarted) {
    window.Alpine.start()
    window.__poofAlpineStarted = true
  }
}

bootReactiveRuntime()

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
