@component('layouts.courier')
<div class="min-h-screen bg-[#070a10] px-4 pb-24 pt-4 text-white">
    <section class="rounded-3xl border border-white/10 bg-[#0d1724] p-4 shadow-[0_12px_36px_rgba(0,0,0,0.45)]">
        <p class="text-xs uppercase tracking-wide text-slate-400">Courier wallet</p>
        <h1 class="mt-1 text-2xl font-black">{{ $wallet['balance_summary']['current_balance_formatted'] }}</h1>
        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
            <div class="rounded-xl border border-white/10 bg-[#101b2b] p-3">
                <p class="text-slate-400">Доступно до виводу</p>
                <p class="mt-1 font-semibold text-poof">{{ $wallet['balance_summary']['available_to_withdraw_formatted'] }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-[#101b2b] p-3">
                <p class="text-slate-400">Held / pending</p>
                <p class="mt-1 font-semibold">{{ $wallet['balance_summary']['held_amount_formatted'] }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-[#101b2b] p-3">
                <p class="text-slate-400">Мінімальний вивід</p>
                <p class="mt-1 font-semibold">{{ $wallet['balance_summary']['minimum_withdrawal_amount_formatted'] }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-[#101b2b] p-3">
                <p class="text-slate-400">Статус</p>
                <p class="mt-1 font-semibold">{{ $wallet['balance_summary']['can_request_withdrawal'] ? 'can_request_withdrawal' : 'blocked' }}</p>
            </div>
        </div>
        @if($wallet['balance_summary']['withdrawal_block_reason'])
            <p class="mt-3 text-xs text-amber-300">withdrawal_block_reason: {{ $wallet['balance_summary']['withdrawal_block_reason'] }}</p>
        @endif
    </section>

    <section class="mt-4 rounded-2xl border border-white/10 bg-[#0d1724] p-4">
        <h2 class="text-sm font-semibold">Запросити вивід</h2>
        <form method="POST" action="{{ route('courier.wallet.withdrawals.request') }}" class="mt-3 space-y-3">
            @csrf
            <input type="number" min="1" name="amount" class="poof-input w-full" placeholder="Сума" required>
            <textarea name="notes" class="poof-input w-full" placeholder="Коментар (опційно)"></textarea>
            <button class="w-full rounded-xl bg-poof py-3 text-sm font-bold text-[#041015]" @disabled(! $wallet['balance_summary']['can_request_withdrawal'])>Надіслати запит</button>
            @error('amount')
                <p class="text-xs text-rose-300">{{ $message }}</p>
            @enderror
        </form>

        <div class="mt-4">
            <p class="text-xs uppercase tracking-wide text-slate-400">Останні заявки</p>
            @forelse($wallet['recent_withdrawal_requests'] as $request)
                <div class="mt-2 flex items-center justify-between rounded-xl border border-white/10 bg-[#101b2b] px-3 py-2 text-xs">
                    <span>{{ $request['amount_formatted'] }}</span>
                    <span class="text-slate-300">{{ $request['status_label'] }}</span>
                </div>
            @empty
                <p class="mt-2 text-xs text-slate-400">Заявок поки немає.</p>
            @endforelse
        </div>
    </section>

    <section class="mt-4 rounded-2xl border border-white/10 bg-[#0d1724] p-4">
        <h2 class="text-sm font-semibold">Банківська карта / payout requisites</h2>
        @if($wallet['payout_requisites']['has_requisites'])
            <div class="mt-3 rounded-xl border border-emerald-400/20 bg-emerald-400/10 p-3 text-xs">
                <p><span class="text-slate-300">Отримувач:</span> {{ $wallet['payout_requisites']['card_holder_name'] }}</p>
                <p class="mt-1"><span class="text-slate-300">Карта:</span> {{ $wallet['payout_requisites']['masked_card_number'] }}</p>
                @if($wallet['payout_requisites']['bank_name'])
                    <p class="mt-1"><span class="text-slate-300">Банк:</span> {{ $wallet['payout_requisites']['bank_name'] }}</p>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('courier.wallet.requisites.save') }}" class="mt-3 space-y-3">
            @csrf
            <input name="card_holder_name" class="poof-input w-full" value="{{ old('card_holder_name', $wallet['payout_requisites']['card_holder_name']) }}" placeholder="Card holder name" required>
            <input name="card_number" class="poof-input w-full" value="{{ old('card_number') }}" placeholder="0000 0000 0000 0000" required>
            <input name="bank_name" class="poof-input w-full" value="{{ old('bank_name', $wallet['payout_requisites']['bank_name']) }}" placeholder="Банк (опційно)">
            <textarea name="notes" class="poof-input w-full" placeholder="Нотатки (опційно)">{{ old('notes', $wallet['payout_requisites']['notes']) }}</textarea>
            <button class="w-full rounded-xl border border-white/20 bg-white/5 py-3 text-sm font-semibold">Зберегти реквізити</button>
        </form>
    </section>

    <section class="mt-4 rounded-2xl border border-white/10 bg-[#0d1724] p-4">
        <h2 class="text-sm font-semibold">Earnings statistics</h2>
        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
            <div class="rounded-xl border border-white/10 bg-[#101b2b] p-3"><p class="text-slate-400">Completed orders</p><p class="mt-1 font-semibold">{{ $wallet['earnings_summary']['completed_orders_count'] }}</p></div>
            <div class="rounded-xl border border-white/10 bg-[#101b2b] p-3"><p class="text-slate-400">Total gross</p><p class="mt-1 font-semibold">{{ $wallet['earnings_summary']['gross_earnings_total_formatted'] }}</p></div>
            <div class="rounded-xl border border-white/10 bg-[#101b2b] p-3"><p class="text-slate-400">Total commission</p><p class="mt-1 font-semibold">{{ $wallet['earnings_summary']['platform_commission_total_formatted'] }}</p></div>
            <div class="rounded-xl border border-white/10 bg-[#101b2b] p-3"><p class="text-slate-400">Total net</p><p class="mt-1 font-semibold">{{ $wallet['earnings_summary']['courier_net_balance_formatted'] }}</p></div>
        </div>

        <div class="mt-4 space-y-3">
            @forelse($wallet['recent_earnings_days'] as $day)
                <article class="rounded-xl border border-white/10 bg-[#101b2b] p-3">
                    <div class="flex items-center justify-between text-xs">
                        <p class="font-semibold">{{ $day['label'] }}</p>
                        <p class="text-slate-300">{{ $day['total_amount_formatted'] }}</p>
                    </div>
                    <div class="mt-2 space-y-2">
                        @foreach($day['orders'] as $order)
                            <div class="flex items-center justify-between text-xs text-slate-300">
                                <span>#{{ $order['order_id'] }} · {{ $order['completed_time'] }}</span>
                                <span>{{ $order['amount_formatted'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>
            @empty
                <p class="text-xs text-slate-400">Ще немає завершених замовлень у вікні історії.</p>
            @endforelse
        </div>
    </section>
</div>
@endcomponent
