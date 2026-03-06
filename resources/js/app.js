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

// 🗺 MAP
import initMap from './poof/map'

// OrderCreate logic
import './poof/order-create'

/* ============================================================
 * POOF INIT
 * ============================================================ */

bottomSheet()
initMap()
