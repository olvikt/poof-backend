<div
    x-data
    x-init="
        const map = L.map('orders-map').setView([50.4501, 30.5234], 11);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap',
        }).addTo(map);

        const statusColors = {
            new: 'blue',
            accepted: 'orange',
            in_progress: 'purple',
            done: 'green',
            cancelled: 'red',
            expired: 'gray',
        };

        const orders = @js($orders);

        orders.forEach(order => {
            const color = statusColors[order.status] ?? 'blue';

            const marker = L.circleMarker(
                [order.lat, order.lng],
                {
                    radius: 8,
                    color: color,
                    fillColor: color,
                    fillOpacity: 0.9,
                }
            ).addTo(map);

            marker.bindPopup(`
                <div style='min-width:180px'>
                    <strong>#${order.id}</strong><br>
                    <small>${order.address}</small><br>
                    <b>Status:</b> ${order.status}<br>
                    <b>Price:</b> ${order.price ?? '-'} UAH<br>
                    <a href='${order.editUrl}'
                       style='display:inline-block;margin-top:6px;color:#2563eb'>
                        Open order →
                    </a>
                </div>
            `);

            marker.on('click', () => {
                window.open(order.editUrl, '_self');
            });
        });
    "
    class="w-full"
>
    <div
        id="orders-map"
        style="height: 420px; border-radius: 12px"
    ></div>
</div>

