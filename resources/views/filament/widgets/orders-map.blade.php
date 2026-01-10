@php
    $mapId = 'orders-map-' . $this->getId();
@endphp

<div
    wire:ignore
    x-data="ordersMapComponent({
        mapId: '{{ $mapId }}',
        orders: @js($orders)
    })"
    x-init="init()"
    class="w-full"
>
    <div id="{{ $mapId }}" style="height:420px;border-radius:12px"></div>
</div>


