import './bootstrap'
import Alpine from 'alpinejs'
import poofTimeCarousel from './poof/carousel'

window.Alpine = Alpine

Alpine.data('poofTimeCarousel', poofTimeCarousel)

Alpine.start()

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
