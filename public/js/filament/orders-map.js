window.ordersMapComponent = function (config) {
    return {
        map: null,
        markersLayer: null,
        pollingTimer: null,

        init() {
            if (typeof L === 'undefined') {
                console.error('Leaflet is not loaded')
                return
            }

            this.$nextTick(() => {
                this.buildMap()

                this.renderMapData({
                    couriers: [],
                    orders: Array.isArray(config.orders) ? config.orders : [],
                })

                this.loadMapData()

                this.pollingTimer = setInterval(() => {
                    this.loadMapData()
                }, 10000)
            })
        },

        buildMap() {
            const el = document.getElementById(config.mapId)
            if (!el) return

            if (this.map) {
                this.map.off()
                this.map.remove()
                this.map = null
            }

            if (this.pollingTimer) {
                clearInterval(this.pollingTimer)
                this.pollingTimer = null
            }

            if (el._leaflet_id) {
                try {
                    delete el._leaflet_id
                } catch (_) {
                    el._leaflet_id = null
                }
            }

            this.map = L.map(config.mapId, {
                zoomControl: true,
                dragging: true,
                scrollWheelZoom: true,
                doubleClickZoom: true,
                boxZoom: true,
                keyboard: true,
            }).setView([50.4501, 30.5234], 6)

            this.markersLayer = L.layerGroup().addTo(this.map)

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
            }).addTo(this.map)
        },

        async loadMapData() {
            if (!this.map || !this.markersLayer) return

            try {
                const response = await fetch('/api/admin/map-data', {
                    headers: {
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                })

                if (!response.ok) {
                    throw new Error(`Map data request failed: ${response.status}`)
                }

                const data = await response.json()

                this.renderMapData(data)

            } catch (error) {
                console.error('Map load error:', error)
            }
        },

        renderMapData(data) {
            if (!this.markersLayer || !this.map) return

            const couriers = Array.isArray(data?.couriers) ? data.couriers : []
            const orders = Array.isArray(data?.orders) ? data.orders : []

            // FIX Leaflet popup crash
            try {
                if (this.map.closePopup) {
                    this.map.closePopup()
                }
            } catch (e) {}

            this.markersLayer.clearLayers()

            const bounds = []

            couriers.forEach((courier) => {

                if (!courier?.lat || !courier?.lng) return

                bounds.push([courier.lat, courier.lng])

                L.circleMarker([courier.lat, courier.lng], {
                    radius: 8,
                    color: '#16a34a',
                    weight: 2,
                    fillColor: '#16a34a',
                    fillOpacity: 0.8,
                })
                    .bindPopup(
                        `<div>
                            <strong>Courier</strong><br>
                            Name: ${courier.name ?? '-'}<br>
                            Vehicle: ${courier.vehicle_type ?? '-'}
                        </div>`
                    )
                    .addTo(this.markersLayer)
            })

            orders.forEach((order) => {

                if (!order?.lat || !order?.lng) return

                bounds.push([order.lat, order.lng])

                L.circleMarker([order.lat, order.lng], {
                    radius: 10,
                    color: '#f97316',
                    weight: 2,
                    fillColor: '#f97316',
                    fillOpacity: 0.85,
                })
                    .bindPopup(
                        `<div>
                            <strong>Order #${order.id}</strong><br>
                            Status: ${order.status ?? '-'}<br>
                            Price: ${order.price ?? '-'} ₴
                        </div>`
                    )
                    .addTo(this.markersLayer)
            })

            // Авто-центрирование карты
            if (bounds.length) {
                try {
                    this.map.fitBounds(bounds, {
                        padding: [60, 60],
                        maxZoom: 15,
                    })
                } catch (e) {}
            }
        },
    }
}
