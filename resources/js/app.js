import './bootstrap'
import Alpine from 'alpinejs'

window.Alpine = Alpine

// CSS
import '../css/app.css'

// UI
import bottomSheet from './poof/bottom-sheet'
import poofTimeCarousel from './poof/carousel'

// 🗺 MAP
import initMap from './poof/map'

// OrderCreate logic
import './poof/order-create'

/* ============================================================
 * POOF INIT
 * ============================================================ */

bottomSheet()
poofTimeCarousel()
initMap()

Alpine.start()
