<div wire:poll.5s style="max-width:700px;margin:0 auto;padding:20px">
    <h2>Доступні замовлення</h2>

    @if(session()->has('success'))
        <div style="background:#e8fff0;border:1px solid #b7f5c8;padding:10px;border-radius:8px;margin:10px 0;">
            {{ session('success') }}
        </div>
    @endif

    @if(session()->has('error'))
        <div style="background:#fff2f2;border:1px solid #ffc9c9;padding:10px;border-radius:8px;margin:10px 0;">
            {{ session('error') }}
        </div>
    @endif

    @if($orders->isEmpty())
        <p>Наразі немає доступних замовлень</p>
    @else
        @foreach($orders as $order)
            <div style="border:1px solid #ccc;padding:12px;margin-bottom:12px;border-radius:8px">
                <strong>Замовлення #{{ $order->id }}</strong>

                <div style="margin-top:6px">📍 {{ $order->address_text }}</div>
                <div>📅 {{ optional($order->scheduled_date)->format('d.m.Y') ?? '—' }}</div>
                <div>⏰ {{ $order->scheduled_time_from ?? '—' }} – {{ $order->scheduled_time_to ?? '—' }}</div>

                <div style="margin-top:6px">💰 {{ $order->price }} ₴</div>

                <form method="POST" action="{{ route('courier.orders.accept', $order) }}" style="margin-top:10px">
                    @csrf
                    <button style="padding:8px 12px;background:#FFD400;border:none;border-radius:6px;font-weight:600">
                        🚴‍♂️ Прийняти замовлення
                    </button>
                </form>
            </div>
        @endforeach
    @endif
</div>
