import './bootstrap'
import Alpine from 'alpinejs'
import poofTimeCarousel from './poof/carousel'


window.Alpine = Alpine

Alpine.data('poofTimeCarousel', poofTimeCarousel)

Alpine.start()


const startLivewire = async () => {
  const { Livewire } = await import('/vendor/livewire/livewire/dist/livewire.esm')
  Livewire.start()
}

startLivewire()

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
