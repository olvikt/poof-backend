window.ordersMapComponent = function (config) {
    return {
        map: null,
        availableCouriersLayer: null,
        busyCouriersLayer: null,
        ordersLayer: null,
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
                this.pollingTimer = setInterval(() => this.loadMapData(), 10000)
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
                try { delete el._leaflet_id } catch (_) { el._leaflet_id = null }
            }

            this.map = L.map(config.mapId, {
                zoomControl: true,
                dragging: true,
                scrollWheelZoom: true,
                doubleClickZoom: true,
                boxZoom: true,
                keyboard: true,
            }).setView([50.4501, 30.5234], 11)

            this.availableCouriersLayer = L.layerGroup().addTo(this.map)
            this.busyCouriersLayer = L.layerGroup().addTo(this.map)
            this.ordersLayer = L.layerGroup().addTo(this.map)

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
            }).addTo(this.map)
        },

        async loadMapData() {
            if (!this.map || !this.availableCouriersLayer || !this.busyCouriersLayer || !this.ordersLayer) return

            try {
                const response = await fetch('/api/dashboard/map', {
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
                console.error(error)
            }
        },

        renderMapData(data) {
            if (!this.availableCouriersLayer || !this.busyCouriersLayer || !this.ordersLayer) return

            const couriers = Array.isArray(data?.couriers) ? data.couriers : []
            const orders = Array.isArray(data?.orders) ? data.orders : []

            this.availableCouriersLayer.clearLayers()
            this.busyCouriersLayer.clearLayers()
            this.ordersLayer.clearLayers()

            const getCourierColor = (status) => {
                if (status === 'online') return '#16a34a'
                if (status === 'assigned') return '#2563eb'
                if (status === 'delivering') return '#f97316'
                return null
            }

            couriers.forEach((courier) => {
                if (!courier?.lat || !courier?.lng) return

                const color = getCourierColor(courier.status)
                if (!color) return

                const marker = L.circleMarker([courier.lat, courier.lng], {
                    radius: 8,
                    color,
                    weight: 2,
                    fillColor: color,
                    fillOpacity: 0.8,
                })
                    .bindPopup(
                        `<div><strong>Courier</strong><br>Name: ${courier.name ?? '-'}<br>Status: ${courier.status ?? '-'}<br>Vehicle: ${courier.vehicle_type ?? '-'}</div>`
                    )

                if (courier.status === 'online') {
                    marker.addTo(this.availableCouriersLayer)
                } else {
                    marker.addTo(this.busyCouriersLayer)
                }
            })

            orders.forEach((order) => {
                if (!order?.lat || !order?.lng) return

                L.circleMarker([order.lat, order.lng], {
                    radius: 10,
                    color: '#f97316',
                    weight: 2,
                    fillColor: '#f97316',
                    fillOpacity: 0.85,
                })
                    .bindPopup(
                        `<div><strong>Order #${order.id}</strong><br>Status: ${order.status ?? '-'}<br>Price: ${order.price ?? '-'} ₴</div>`
                    )
                    .addTo(this.ordersLayer)
            })
        },
    }
}
