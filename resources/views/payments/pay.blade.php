<div style="max-width:500px;margin:40px auto;padding:20px;border:1px solid #ddd">
    <h2>Оплата замовлення #{{ $order->id }}</h2>

    <p><strong>Сума:</strong> {{ $order->price }} ₴</p>

    <p style="margin-top:15px;color:#666">
        Це тестова сторінка оплати.<br>
        Тут буде підключена платіжна система.
    </p>
	<form method="POST" action="{{ route('client.payments.dev-pay', $order) }}">
		@csrf
		<button style="padding:10px 14px;background:#22c55e;color:#fff;border:none">
			✅ Імітувати оплату (DEV)
		</button>
	</form>
    <a href="{{ route('client.orders') }}"
       style="display:inline-block;margin-top:20px;padding:10px 14px;background:#FFD400;color:#000;text-decoration:none">
        Повернутися до замовлень
    </a>
</div>
