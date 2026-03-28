import './bootstrap'
import Alpine from 'alpinejs'
import poofTimeCarousel from './poof/carousel'
import addressAutocomplete from './address-autocomplete'

window.Alpine = Alpine

document.addEventListener('alpine:init', () => {
  Alpine.data('poofTimeCarousel', poofTimeCarousel)
  Alpine.data('addressAutocomplete', addressAutocomplete)
})

if (window.Livewire?.start) {
  window.Livewire.start()
}

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
