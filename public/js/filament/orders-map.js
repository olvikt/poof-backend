/**
 * Global Alpine component for Orders Map (Filament compatible)
 * Drag disabled, zoom + fitBounds used instead (admin-friendly UX)
 */
window.ordersMapComponent = function (config) {
    return {
        map: null,
        markers: [],

        /**
         * Alpine init
         */
        init() {
            if (typeof L === 'undefined') {
                console.error('Leaflet is not loaded')
                return
            }

            this.$nextTick(() => this.buildMap())
        },

        /**
         * Build / rebuild map safely
         */
        buildMap() {
            const el = document.getElementById(config.mapId)
            if (!el) return

            /* ---------------- destroy previous map ---------------- */

            if (this.map) {
                try {
                    this.map.off()
                    this.map.remove()
                } catch (_) {}
                this.map = null
            }

            if (el._leaflet_id) {
                try { delete el._leaflet_id } catch (_) { el._leaflet_id = null }
            }

            this.markers = []

            /* ---------------- create map ---------------- */

            this.map = L.map(el, {
                dragging: false,          // ❌ no drag (Filament safe)
                scrollWheelZoom: true,    // ✅ zoom with wheel
                doubleClickZoom: true,
                boxZoom: true,
                keyboard: false,
                tap: false,
            }).setView([50.4501, 30.5234], 11)

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
            }).addTo(this.map)

            /* ---------------- markers ---------------- */

            config.orders.forEach(order => {
                if (!order.lat || !order.lng) return

                // small offset so markers with same coords are visible
                const jitter = () => (Math.random() - 0.5) * 0.0003

                const marker = L.circleMarker(
                    [order.lat + jitter(), order.lng + jitter()],
                    {
                        radius: 8,
                        color: '#111827',
                        weight: 2,
                        fillColor: this.statusColor(order.status),
                        fillOpacity: 1,
                    }
                )
                .addTo(this.map)
                .bindPopup(this.buildPopup(order))

                this.markers.push(marker)
            })

            /* ---------------- fit map to markers ---------------- */

            this.fitToOrders()
        },

        /**
         * Center / fit all orders on map
         */
        fitToOrders() {
            if (!this.map || this.markers.length === 0) return

            const bounds = L.latLngBounds(
                this.markers.map(marker => marker.getLatLng())
            )

            if (this.markers.length === 1) {
                this.map.setView(bounds.getCenter(), 15)
            } else {
                this.map.fitBounds(bounds, {
                    padding: [40, 40],
                    maxZoom: 16,
                })
            }
        },

        /**
         * Popup content
         */
        buildPopup(order) {
            const wrapper = document.createElement('div')
            wrapper.style.minWidth = '180px'

            wrapper.innerHTML = `
                <div style="font-weight:600">Order #${order.id}</div>
                <div style="font-size:12px">${order.address ?? ''}</div>
                <div style="margin:6px 0;font-weight:600">
                    ${order.price ?? '-'} ₴
                </div>
                <div style="font-size:12px;color:#6b7280">
                    ${order.status}
                </div>
                <a href="${order.editUrl}"
                   style="display:inline-block;margin-top:8px;font-weight:600;color:#2563eb"
                   onclick="event.stopPropagation()">
                    Open order →
                </a>
            `

            return wrapper
        },

        /**
         * Status → color
         */
        statusColor(status) {
            switch (status) {
                case 'new':         return '#3b82f6'
                case 'accepted':    return '#f59e0b'
                case 'in_progress': return '#6366f1'
                case 'done':        return '#22c55e'
                case 'cancelled':   return '#ef4444'
                default:            return '#6b7280'
            }
        },
    }
}
