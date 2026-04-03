<div class="min-h-screen rounded-xl bg-gray-950 px-4 pb-28 pt-4 text-white shadow-[0_0_0_1px_rgba(74,222,128,0.25)]">
    <h1 class="text-xl font-bold">Підписка</h1>
    <p class="mt-1 text-sm text-gray-400">Керуйте підписками для себе та близьких в одному місці.</p>

    <section class="mt-4 grid grid-cols-2 gap-3 text-sm">
        <div class="rounded-2xl border border-gray-800 bg-gray-900 p-3">
            <p class="text-gray-400">Активні</p>
            <p class="mt-1 text-xl font-bold text-yellow-400">{{ $stats['active'] }}</p>
        </div>
        <div class="rounded-2xl border border-gray-800 bg-gray-900 p-3">
            <p class="text-gray-400">На паузі</p>
            <p class="mt-1 text-xl font-bold text-yellow-400">{{ $stats['paused'] }}</p>
        </div>
        <div class="rounded-2xl border border-gray-800 bg-gray-900 p-3">
            <p class="text-gray-400">Завершені</p>
            <p class="mt-1 text-xl font-bold text-yellow-400">{{ $stats['completed'] }}</p>
        </div>
        <div class="rounded-2xl border border-gray-800 bg-gray-900 p-3">
            <p class="text-gray-400">Найближчі продовження</p>
            <p class="mt-1 text-xl font-bold text-yellow-400">{{ $stats['renewals_soon'] }}</p>
        </div>
    </section>

    <div class="mt-3 rounded-2xl border border-gray-800 bg-gray-900 p-3">
        <p class="text-sm text-gray-400">Оплачено по підписках</p>
        <p class="mt-1 text-2xl font-extrabold text-white">{{ number_format($stats['total_paid'], 0, ',', ' ') }} ₴</p>
    </div>

    <section class="mt-5 space-y-4">
        @forelse($subscriptions as $subscription)
            <article class="rounded-2xl border border-gray-800 bg-gray-900 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-base font-semibold">{{ $subscription->plan?->name ?? 'План підписки' }}</p>
                        <p class="text-sm text-gray-400">{{ $subscription->address?->address_text ?? 'Адреса буде додана під час оформлення' }}</p>
                    </div>
                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $subscription->status === \App\Models\ClientSubscription::STATUS_ACTIVE ? 'bg-green-500/20 text-green-300' : ($subscription->status === \App\Models\ClientSubscription::STATUS_PAUSED ? 'bg-yellow-500/20 text-yellow-300' : 'bg-gray-700 text-gray-300') }}">
                        {{ $subscription->status_label }}
                    </span>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-3 text-sm text-gray-300">
                    <p>Частота: <span class="text-white">{{ $subscription->frequency_label }}</span></p>
                    <p>Місячна вартість: <span class="text-white">{{ number_format((int) ($subscription->plan?->monthly_price ?? 0), 0, ',', ' ') }} ₴</span></p>
                    <p>Початок: <span class="text-white">{{ optional($subscription->created_at)->format('d.m.Y') }}</span></p>
                    <p>Активна до: <span class="text-white">{{ optional($subscription->ends_at)->format('d.m.Y') ?? '—' }}</span></p>
                </div>

                <p class="mt-2 text-xs text-gray-400">
                    Автопродовження {{ $subscription->auto_renew ? 'увімкнено' : 'вимкнено' }}
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a
                        href="{{ route('client.order.create', ['address_id' => $subscription->address_id, 'subscription_id' => $subscription->id, 'source' => 'subscription_renew']) }}"
                        class="rounded-xl bg-yellow-400 px-4 py-2 text-sm font-semibold text-black"
                    >
                        Продовжити
                    </a>

                    <button wire:click="toggleAutoRenew({{ $subscription->id }})" type="button" class="rounded-xl border border-gray-700 px-3 py-2 text-sm text-gray-200">
                        {{ $subscription->auto_renew ? 'Вимкнути автопродовження' : 'Увімкнути автопродовження' }}
                    </button>

                    @if($subscription->status === \App\Models\ClientSubscription::STATUS_ACTIVE)
                        <button wire:click="pause({{ $subscription->id }})" type="button" class="rounded-xl border border-gray-700 px-3 py-2 text-sm text-gray-200">Пауза</button>
                    @elseif($subscription->status === \App\Models\ClientSubscription::STATUS_PAUSED)
                        <button wire:click="resume({{ $subscription->id }})" type="button" class="rounded-xl border border-gray-700 px-3 py-2 text-sm text-gray-200">Відновити</button>
                    @endif

                    @if($subscription->status !== \App\Models\ClientSubscription::STATUS_CANCELLED)
                        <button wire:click="cancel({{ $subscription->id }})" type="button" class="rounded-xl border border-red-500/50 px-3 py-2 text-sm text-red-200">Зупинити</button>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-700 bg-gray-900/70 p-4 text-sm text-gray-300">
                Підписок поки немає. Оформіть першу підписку під час створення замовлення.
            </div>
        @endforelse
    </section>
</div>
