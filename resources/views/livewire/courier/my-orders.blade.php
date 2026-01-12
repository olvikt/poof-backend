<div style="max-width:700px;margin:0 auto;padding:20px">
    <h2>🚴‍♂️ Мої замовлення</h2>

    @if($orders->isEmpty())
        <p>Активних замовлень немає</p>
    @else
        @foreach($orders as $order)
            <div style="border:1px solid #ddd;padding:12px;margin-bottom:12px">
                <strong>Замовлення #{{ $order->id }}</strong>

                <div>📍 {{ $order->address_text }}</div>
                <div>🕒 {{ $order->scheduled_time_from }} – {{ $order->scheduled_time_to }}</div>
                <div>💰 {{ $order->price }} ₴</div>
                <div>📦 Статус: {{ \App\Models\Order::STATUS_LABELS[$order->status] }}</div>

                {{-- ACTIONS --}}
                <div style="margin-top:10px">
                    @if($order->status === \App\Models\Order::STATUS_ACCEPTED)
                        <form method="POST"
                              action="{{ route('courier.orders.start', $order) }}">
                            @csrf
                            <button style="padding:8px 14px;background:#3b82f6;color:#fff;border:none">
                                ▶️ Почати
                            </button>
                        </form>
                    @endif

                    @if($order->status === \App\Models\Order::STATUS_IN_PROGRESS)
                        <form method="POST"
                              action="{{ route('courier.orders.complete', $order) }}">
                            @csrf
                            <button style="padding:8px 14px;background:#22c55e;color:#fff;border:none">
                                ✅ Завершити
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    @endif
</div>
