<div class="min-h-screen rounded-xl bg-gray-950 px-4 pb-28 pt-4 text-white shadow-[0_0_0_1px_rgba(74,222,128,0.25)]" @if($embedded) data-more-shell-screen="subscriptions" @endif>
    @unless($embedded)
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold">Підписка</h1>
            <p class="mt-1 text-sm text-gray-400">Керуйте підписками для себе та близьких в одному місці.</p>
        </div>
        <a href="{{ route('client.home') }}" class="rounded-xl border border-gray-700 px-3 py-2 text-sm text-gray-200">Закрити</a>
    </div>
    @endunless

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

    <div class="mt-5 flex gap-2">
        <button
            wire:click="switchTab('active')"
            type="button"
            class="flex-1 rounded-lg px-4 py-1.5 text-center text-sm font-semibold {{ $tab === 'active' ? 'bg-yellow-400 text-black' : 'bg-gray-800 text-gray-400' }}"
        >
            Активні
        </button>
        <button
            wire:click="switchTab('archive')"
            type="button"
            class="flex-1 rounded-lg px-4 py-1.5 text-center text-sm font-semibold {{ $tab === 'archive' ? 'bg-yellow-400 text-black' : 'bg-gray-800 text-gray-400' }}"
        >
            Архів
        </button>
    </div>

    @php($visibleSubscriptions = $tab === 'archive' ? $this->archivedSubscriptions() : $this->activeSubscriptions())

    <section class="mt-4 space-y-4">
        @forelse($visibleSubscriptions as $subscription)
            <article class="rounded-2xl border border-gray-800 bg-gray-900 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-base font-semibold">{{ $subscription->plan?->name ?? 'План підписки' }}</p>
                        <p class="text-sm text-gray-400">{{ $subscription->address?->address_text ?? 'Адреса буде додана під час оформлення' }}</p>
                    </div>
                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $subscription->status_badge_classes }}">
                        {{ $subscription->ui_status_label }}
                    </span>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-3 text-sm text-gray-300">
                    <p>Частота: <span class="text-white">{{ $subscription->frequency_label }}</span></p>
                    <p>Місячна вартість: <span class="text-white">{{ number_format((int) ($subscription->plan?->monthly_price ?? 0), 0, ',', ' ') }} ₴</span></p>
                    <p>Початок: <span class="text-white">{{ optional($subscription->startsAtForDisplay())->format('d.m.Y') ?? '—' }}</span></p>
                    <p>Активна до: <span class="text-white">{{ optional($subscription->activeUntilForDisplay())->format('d.m.Y') ?? '—' }}</span></p>
                </div>

                @if($tab === 'active')
                    <div class="mt-3 flex items-center justify-between rounded-xl border border-gray-800 bg-gray-950/60 px-3 py-2">
                        <span class="text-sm text-gray-200">Автопродовження</span>
                        <button
                            wire:click="toggleAutoRenew({{ $subscription->id }})"
                            type="button"
                            @disabled(!$subscription->canToggleAutoRenew())
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $subscription->auto_renew ? 'bg-yellow-400' : 'bg-gray-700' }} disabled:cursor-not-allowed disabled:opacity-50"
                            aria-label="Перемкнути автопродовження"
                        >
                            <span class="inline-block h-5 w-5 transform rounded-full bg-white transition {{ $subscription->auto_renew ? 'translate-x-5' : 'translate-x-1' }}"></span>
                        </button>
                    </div>
                    @if(! $subscription->canToggleAutoRenew())
                        <p class="mt-1 text-xs text-gray-500">Автопродовження можна налаштувати після першої оплати.</p>
                    @endif
                @endif

                <div class="mt-4 flex flex-wrap gap-2">
                    @if($tab === 'active' && $subscription->canPay())
                        <form method="POST" action="{{ route('client.subscriptions.pay', $subscription) }}">
                            @csrf
                            <button type="submit" class="rounded-xl bg-yellow-400 px-4 py-2 text-sm font-semibold text-black">Оплатити</button>
                        </form>
                        <button wire:click="cancel({{ $subscription->id }})" type="button" class="rounded-xl border border-red-500/50 px-3 py-2 text-sm text-red-200">Скасувати</button>
                    @elseif($tab === 'active')
                        @if($subscription->canResume())
                            <button wire:click="resume({{ $subscription->id }})" type="button" class="rounded-xl border border-gray-700 px-3 py-2 text-sm text-gray-200">Відновити</button>
                        @elseif($subscription->canPause())
                            <button wire:click="pause({{ $subscription->id }})" type="button" class="rounded-xl border border-gray-700 px-3 py-2 text-sm text-gray-200">Пауза</button>
                        @endif
                        @if($subscription->canRenew())
                            <form method="POST" action="{{ route('client.subscriptions.renew', $subscription) }}">
                                @csrf
                                <button type="submit" class="rounded-xl bg-yellow-400 px-4 py-2 text-sm font-semibold text-black">Продовжити</button>
                            </form>
                        @endif
                        @if($subscription->canOpenDetails())
                            <button wire:click="openDetails({{ $subscription->id }})" type="button" class="rounded-xl border border-gray-700 px-3 py-2 text-sm text-gray-200">Докладніше</button>
                        @endif
                    @else
                        <span class="rounded-xl border border-gray-700 px-3 py-2 text-sm text-gray-400">В архіві</span>
                        @if($subscription->display_status === \App\Models\ClientSubscription::STATUS_COMPLETED && $subscription->canRenew())
                        <form method="POST" action="{{ route('client.subscriptions.renew', $subscription) }}">
                            @csrf
                            <button type="submit" class="rounded-xl border border-gray-700 px-3 py-2 text-sm text-gray-200">Продовжити</button>
                        </form>
                        @endif
                        @if($subscription->canOpenDetails())
                            <button wire:click="openDetails({{ $subscription->id }})" type="button" class="rounded-xl border border-gray-700 px-3 py-2 text-sm text-gray-200">Докладніше</button>
                        @endif
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-700 bg-gray-900/70 p-4 text-sm text-gray-300">
                {{ $tab === 'archive'
                    ? 'Архів підписок порожній.'
                    : 'Підписок поки немає. Оформіть першу підписку під час створення замовлення.' }}
            </div>
        @endforelse
    </section>

    <x-poof.modal wire:model="showDetailsModal" maxWidth="max-w-lg">
        <div class="space-y-3">
            <h3 class="text-lg font-semibold text-white">Докладніше</h3>
            <p class="text-sm text-gray-300">{{ $details['plan_name'] ?? 'План' }}</p>
            <div class="grid grid-cols-2 gap-2 text-sm text-gray-300">
                <p>Період: <span class="text-white">{{ ($details['period_start'] ?? '—') }} — {{ ($details['period_end'] ?? '—') }}</span></p>
                <p>Статус: <span class="text-white">{{ $details['status'] ?? '—' }}</span></p>
                <p>Виконано: <span class="text-white">{{ $details['completed_runs'] ?? 0 }} з {{ $details['total_runs'] ?? 0 }}</span></p>
                <p>Залишилось: <span class="text-white">{{ $details['remaining_runs'] ?? 0 }}</span></p>
                <p>Наступний винос: <span class="text-white">{{ $details['next_planned'] ?? '—' }}</span></p>
                <p>Автопродовження: <span class="text-white">{{ !empty($details['auto_renew']) ? 'Увімкнено' : 'Вимкнено' }}</span></p>
            </div>

            <div class="max-h-56 overflow-y-auto rounded-xl border border-gray-800 bg-gray-950 p-3">
                <div class="grid grid-cols-5 gap-2">
                    @foreach(($details['timeline'] ?? []) as $run)
                        <div class="text-center">
                            <div class="mx-auto h-4 w-4 rounded-full border {{ $run['completed'] ? 'border-yellow-400 bg-yellow-400' : 'border-gray-600 bg-transparent' }}"></div>
                            <div class="mt-1 text-[10px] text-gray-400">{{ $run['date'] }}</div>
                            <div class="mt-0.5 text-[9px] text-gray-500">{{ $run['status'] ?? 'Очікується' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-gray-800 bg-gray-950 p-3">
                <p class="text-xs uppercase tracking-wide text-gray-500">Історія виносів</p>
                <div class="mt-2 space-y-2">
                    @forelse(($details['history'] ?? []) as $executionOrder)
                        <div class="flex items-center justify-between text-xs text-gray-300">
                            <span>#{{ $executionOrder['id'] }} · {{ $executionOrder['date'] }}</span>
                            <span class="text-gray-400">{{ $executionOrder['status'] }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-gray-500">Виноси ще не створені.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </x-poof.modal>
</div>
