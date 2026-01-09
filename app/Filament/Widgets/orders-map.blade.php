<x-filament-widgets::widget>
    <x-filament::section>
        <div
            id="orders-map"
            style="height: 500px; width: 100%;"
            x-data
            x-init="
                const map = L.map('orders-map').setView([50.4501, 30.5234], 12);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap',
                }).addTo(map);

                const orders = @js($orders);

                orders.forEach(order => {
                    const marker = L.marker([order.lat, order.lng]).addTo(map);
                    marker.bindPopup(
                        `Заказ #${order.id}<br>Статус: ${order.status}`
                    );
                });
            "
        ></div>
    </x-filament::section>
</x-filament-widgets::widget>
