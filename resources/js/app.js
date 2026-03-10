import './bootstrap'
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm'
import poofTimeCarousel from './poof/carousel'
import addressAutocomplete from './address-autocomplete'

window.Alpine = Alpine

document.addEventListener('alpine:init', () => {
  Alpine.data('poofTimeCarousel', poofTimeCarousel)
  Alpine.data('addressAutocomplete', addressAutocomplete)
})

Livewire.start()

// CSS
import '../css/app.css'

// UI
import './poof/bottom-sheet'

// MAP
import initMap from './poof/map'

// OrderCreate
import './poof/order-create'

initMap()

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
  })
}
