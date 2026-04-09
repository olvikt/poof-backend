@component('layouts.courier')
<div class="min-h-screen bg-[#070a10] px-4 pb-24 pt-4 text-white">
    <section class="rounded-3xl border border-white/10 bg-[#0d1724] p-4 shadow-[0_12px_36px_rgba(0,0,0,0.45)]">
        <p class="text-xs uppercase tracking-wide text-slate-400">Гаманець курʼєра</p>
        <h1 class="mt-1 text-2xl font-black">{{ $wallet['balance_summary']['current_balance_formatted'] }}</h1>
        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
            <div class="rounded-xl border border-white/10 bg-[#101b2b] p-3">
                <p class="text-slate-400">Доступно до виводу</p>
                <p class="mt-1 font-semibold text-poof">{{ $wallet['balance_summary']['available_to_withdraw_formatted'] }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-[#101b2b] p-3">
                <p class="text-slate-400">Утримано / в обробці</p>
                <p class="mt-1 font-semibold">{{ $wallet['balance_summary']['held_amount_formatted'] }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-[#101b2b] p-3">
                <p class="text-slate-400">Мінімальний вивід</p>
                <p class="mt-1 font-semibold">{{ $wallet['balance_summary']['minimum_withdrawal_amount_formatted'] }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-[#101b2b] p-3">
                <p class="text-slate-400">Статус</p>
                <p class="mt-1 font-semibold">{{ $wallet['balance_summary']['can_request_withdrawal'] ? 'Вивід доступний' : 'Вивід тимчасово недоступний' }}</p>
            </div>
        </div>
        @if($wallet['balance_summary']['withdrawal_block_message'])
            <p class="mt-3 text-xs text-amber-300">{{ $wallet['balance_summary']['withdrawal_block_message'] }}</p>
        @endif

        <div class="mt-4 flex justify-end">
            <button
                type="button"
                class="rounded-xl px-4 py-2 text-sm font-bold transition {{ $wallet['balance_summary']['can_request_withdrawal'] ? 'bg-poof text-[#041015]' : 'cursor-not-allowed border border-white/15 bg-white/10 text-slate-400' }}"
                @disabled(! $wallet['balance_summary']['can_request_withdrawal'])
                onclick="window.dispatchEvent(new CustomEvent('sheet:open',{detail:{name:'courierWalletWithdrawal'}}))"
            >
                Запросити вивід
            </button>
        </div>
    </section>

    <section class="mt-4 rounded-2xl border border-white/10 bg-[#0d1724] p-4">
        <div>
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
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold">Банківська карта</h2>
            <button
                type="button"
                class="flex h-7 w-7 items-center justify-center rounded-full bg-poof text-lg font-bold leading-none text-[#041015]"
                aria-label="Додати або змінити реквізити"
                onclick="window.dispatchEvent(new CustomEvent('sheet:open',{detail:{name:'courierWalletCard'}}))"
            >
                +
            </button>
        </div>
        @if($wallet['payout_requisites']['has_requisites'])
            <div class="mt-3 rounded-xl border border-emerald-400/20 bg-emerald-400/10 p-3 text-xs">
                <p><span class="text-slate-300">Отримувач:</span> {{ $wallet['payout_requisites']['card_holder_name'] }}</p>
                <p class="mt-1"><span class="text-slate-300">Карта:</span> {{ $wallet['payout_requisites']['masked_card_number'] }}</p>
                @if($wallet['payout_requisites']['bank_name'])
                    <p class="mt-1"><span class="text-slate-300">Банк:</span> {{ $wallet['payout_requisites']['bank_name'] }}</p>
                @endif
            </div>
        @else
            <p class="mt-3 text-xs text-slate-400">Додайте реквізити для отримання виплат.</p>
        @endif
    </section>

    <section class="mt-4 rounded-2xl border border-white/10 bg-[#0d1724] p-4">
        <h2 class="text-sm font-semibold">Статистика заробітку</h2>
        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
            <div class="rounded-xl border border-white/10 bg-[#101b2b] p-3"><p class="text-slate-400">Завершені замовлення</p><p class="mt-1 font-semibold">{{ $wallet['earnings_summary']['completed_orders_count'] }}</p></div>
            <div class="rounded-xl border border-white/10 bg-[#101b2b] p-3"><p class="text-slate-400">Загальна сума брутто</p><p class="mt-1 font-semibold">{{ $wallet['earnings_summary']['gross_earnings_total_formatted'] }}</p></div>
            <div class="rounded-xl border border-white/10 bg-[#101b2b] p-3"><p class="text-slate-400">Загальна комісія</p><p class="mt-1 font-semibold">{{ $wallet['earnings_summary']['platform_commission_total_formatted'] }}</p></div>
            <div class="rounded-xl border border-white/10 bg-[#101b2b] p-3"><p class="text-slate-400">Загальна сума нетто</p><p class="mt-1 font-semibold">{{ $wallet['earnings_summary']['courier_net_balance_formatted'] }}</p></div>
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

    <x-poof.ui.bottom-sheet name="courierWalletWithdrawal" title="Запросити вивід" bodyClass="px-4 pb-6 pt-4">
        <form method="POST" action="{{ route('courier.wallet.withdrawals.request') }}" class="space-y-3">
            @csrf
            <input type="number" min="1" name="amount" class="poof-input w-full" placeholder="Сума" value="{{ old('amount') }}" required>
            <textarea name="notes" class="poof-input w-full" placeholder="Коментар (опційно)">{{ old('notes') }}</textarea>
            <button class="w-full rounded-xl bg-poof py-3 text-sm font-bold text-[#041015]" @disabled(! $wallet['balance_summary']['can_request_withdrawal'])>Надіслати запит</button>
            @error('amount')
                <p class="text-xs text-rose-300">{{ $message }}</p>
            @enderror
        </form>
    </x-poof.ui.bottom-sheet>

    <x-poof.ui.bottom-sheet name="courierWalletCard" title="Реквізити для виплат" bodyClass="px-4 pb-6 pt-4">
        <form
            method="POST"
            action="{{ route('courier.wallet.requisites.save') }}"
            class="space-y-3"
            x-data="{
                cardNumber: @js(old('card_number')),
                bankName: @js(old('bank_name', $wallet['payout_requisites']['bank_name'])),
                formatCardNumber(event) {
                    const digits = (event.target.value || '').replace(/\D/g, '').slice(0, 16);
                    this.cardNumber = digits.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
                },
                get cardNumberIsValid() {
                    return (this.cardNumber || '').replace(/\D/g, '').length === 16;
                },
                get canSubmit() {
                    return this.cardNumberIsValid && ((this.bankName || '').trim().length > 0);
                }
            }"
        >
            @csrf
            <input name="card_holder_name" class="poof-input w-full" value="{{ old('card_holder_name', $wallet['payout_requisites']['card_holder_name']) }}" placeholder="Імʼя отримувача (опційно)">
            <input
                name="card_number"
                class="poof-input w-full"
                x-model="cardNumber"
                x-on:input="formatCardNumber($event)"
                placeholder="0000 0000 0000 0000"
                maxlength="19"
                inputmode="numeric"
                autocomplete="cc-number"
                required
            >
            <input name="bank_name" class="poof-input w-full" x-model="bankName" value="{{ old('bank_name', $wallet['payout_requisites']['bank_name']) }}" placeholder="Назва банку" required>
            <textarea name="notes" class="poof-input w-full" placeholder="Нотатки (опційно)">{{ old('notes', $wallet['payout_requisites']['notes']) }}</textarea>
            <button
                class="w-full rounded-xl py-3 text-sm font-semibold transition"
                :class="canSubmit ? 'bg-poof text-[#041015]' : 'cursor-not-allowed border border-white/20 bg-white/5 text-slate-300'"
                :disabled="!canSubmit"
            >
                Зберегти реквізити
            </button>
            @error('card_number')
                <p class="text-xs text-rose-300">{{ $message }}</p>
            @enderror
            @error('bank_name')
                <p class="text-xs text-rose-300">{{ $message }}</p>
            @enderror
        </form>
    </x-poof.ui.bottom-sheet>
</div>

@if($errors->has('amount'))
    <script>
        window.addEventListener('load', () => {
            window.dispatchEvent(new CustomEvent('sheet:open', { detail: { name: 'courierWalletWithdrawal' } }));
        });
    </script>
@endif

@if($errors->has('card_number') || $errors->has('bank_name') || $errors->has('card_holder_name'))
    <script>
        window.addEventListener('load', () => {
            window.dispatchEvent(new CustomEvent('sheet:open', { detail: { name: 'courierWalletCard' } }));
        });
    </script>
@endif
@endcomponent
