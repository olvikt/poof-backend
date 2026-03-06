import './bootstrap'
import Alpine from 'alpinejs'
import collapse from './alpine-collapse'
import poofTimeCarousel from './poof/carousel'

Alpine.plugin(collapse)

window.Alpine = Alpine

Alpine.data('poofTimeCarousel', poofTimeCarousel)

Alpine.start()

// CSS
import '../css/app.css'

// UI
import bottomSheet from './poof/bottom-sheet'

// 🗺 MAP
import initMap from './poof/map'

// OrderCreate logic
import './poof/order-create'

/* ============================================================
 * POOF INIT
 * ============================================================ */

bottomSheet()
initMap()

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
  })
}
