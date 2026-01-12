<div style="max-width:700px;margin:0 auto;padding:20px">

    <h2>Мої замовлення</h2>

    {{-- АКТИВНЫЕ --}}
    <h3 style="margin-top:20px">Активні</h3>

    @if($activeOrders->isEmpty())
        <p>Активних замовлень немає</p>
    @else
        @foreach($activeOrders as $order)
 <div style="border:1px solid #ccc;padding:12px;margin-bottom:12px;border-radius:8px">
    <strong>Замовлення #{{ $order->id }}</strong>

    <div style="margin-top:6px">📍 {{ $order->address_text }}</div>
  <div>
    📅 {{ optional($order->scheduled_date)->format('d.m.Y') ?? 'Дата не вказана' }}
</div>
    <div>
    ⏰ {{ $order->scheduled_time_from ?? '—' }} – {{ $order->scheduled_time_to ?? '—' }}
</div>

    <div style="margin-top:6px">
        💰 Ціна:
        @if($order->is_trial)
            <strong style="color:green">0 ₴ (TEST)</strong>
        @else
            <strong>{{ $order->price }} ₴</strong>
        @endif
    </div>

    {{-- PAYMENT STATUS --}}
    <div style="margin-top:6px">
        💳 Оплата:
        @if($order->payment_status === \App\Models\Order::PAY_PENDING)
            <span style="color:#d97706;font-weight:600">
                {{ \App\Models\Order::PAYMENT_LABELS[$order->payment_status] }}
            </span>
        @else
            <span style="color:green;font-weight:600">
                {{ \App\Models\Order::PAYMENT_LABELS[$order->payment_status] }}
            </span>
        @endif
    </div>

    {{-- ORDER STATUS --}}
    <div style="margin-top:4px">
        🚚 Статус:
        <strong>
            {{ \App\Models\Order::STATUS_LABELS[$order->status] ?? $order->status }}
        </strong>
    </div>

{{-- ACTIONS --}}
<div style="margin-top:10px">
    @if($order->payment_status === \App\Models\Order::PAY_PENDING)
        <a href="{{ route('client.payments.pay', $order) }}"
           style="display:inline-block;padding:8px 12px;background:#FFD400;color:#000;border-radius:6px;font-weight:600;text-decoration:none">
            💳 Оплатити {{ $order->price }} ₴
        </a>
    @endif
</div>
</div>
        @endforeach
    @endif

    {{-- ИСТОРИЯ --}}
    <h3 style="margin-top:30px">Історія</h3>

    @if($historyOrders->isEmpty())
        <p>Історія замовлень порожня</p>
    @else
        @foreach($historyOrders as $order)
            <div style="border:1px solid #eee;padding:10px;margin-bottom:10px;background:#fafafa">
                <strong>Замовлення #{{ $order->id }}</strong>

                <div>Адреса: {{ $order->address_text }}</div>
                <div>Дата: {{ $order->scheduled_date->format('d.m.Y') }}</div>

                <div>
                    Ціна:
                    @if($order->is_trial)
                        <strong style="color:green">0 ₴ (TEST)</strong>
                    @else
                        {{ $order->price }} ₴
                    @endif
                </div>

                <div>Статус: {{ \App\Models\Order::STATUS_LABELS[$order->status] ?? $order->status }}</div>
            </div>
        @endforeach
    @endif

</div>

