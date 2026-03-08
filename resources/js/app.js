import './bootstrap'
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm.js'
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
import bottomSheet from './poof/bottom-sheet'

// MAP
import initMap from './poof/map'

// OrderCreate
import './poof/order-create'

bottomSheet()
initMap()

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
  })
}
