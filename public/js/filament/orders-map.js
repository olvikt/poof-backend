window.ordersMapComponent = function (config) {
    return {
        map: null,
        markersLayer: null,
        pollingTimer: null,
        hasCentered: false,

        init() {

            if (typeof L === 'undefined') {
                console.error('Leaflet not loaded')
                return
            }

            this.$nextTick(() => {

                this.buildMap()

                this.renderMapData({
                    couriers: [],
                    orders: Array.isArray(config.orders) ? config.orders : []
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

            this.map = L.map(config.mapId, {
                zoomControl: true,
                dragging: true,
                scrollWheelZoom: true,
                doubleClickZoom: true,
                boxZoom: true,
                keyboard: true
            }).setView([48.45, 35.05], 12)

            this.markersLayer = L.layerGroup().addTo(this.map)

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap'
            }).addTo(this.map)

        },

        async loadMapData() {

            if (!this.map) return

            try {

                const response = await fetch('/api/admin/map-data', {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' }
                })

                if (!response.ok) {
                    throw new Error('Map data error: ' + response.status)
                }

                const data = await response.json()

                this.renderMapData(data)

            } catch (e) {

                console.error('Map fetch error', e)

            }

        },

        renderMapData(data) {

            if (!this.map || !this.markersLayer) return

            const couriers = Array.isArray(data?.couriers) ? data.couriers : []
            const orders = Array.isArray(data?.orders) ? data.orders : []

            // закрываем popup перед очисткой
            try {
                this.map.closePopup()
            } catch (e) {}

            this.markersLayer.clearLayers()

            const bounds = []

            couriers.forEach(courier => {

                if (!courier.lat || !courier.lng) return

                bounds.push([courier.lat, courier.lng])

                L.circleMarker([courier.lat, courier.lng], {
                    radius: 8,
                    color: "#16a34a",
                    weight: 2,
                    fillColor: "#16a34a",
                    fillOpacity: 0.8
                })
                .bindPopup(
                    `<b>Courier</b><br>
                     ${courier.name ?? '-'}`
                )
                .addTo(this.markersLayer)

            })

            orders.forEach(order => {

                if (!order.lat || !order.lng) return

                bounds.push([order.lat, order.lng])

                L.circleMarker([order.lat, order.lng], {
                    radius: 10,
                    color: "#f97316",
                    weight: 2,
                    fillColor: "#f97316",
                    fillOpacity: 0.9
                })
                .bindPopup(
                    `<b>Order #${order.id}</b><br>
                     ${order.status}<br>
                     ${order.price} ₴`
                )
                .addTo(this.markersLayer)

            })

            // центрируем карту только один раз
            if (!this.hasCentered && bounds.length) {

                try {

                    this.map.fitBounds(bounds, {
                        padding: [50,50],
                        maxZoom: 14
                    })

                    this.hasCentered = true

                } catch (e) {}

            }

        }

    }
}

