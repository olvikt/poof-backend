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



    <div class="mt-4">
        <a
            href="{{ route('client.order.create', ['open_subscription_plans' => 1]) }}"
            class="inline-flex w-full items-center justify-center rounded-xl bg-yellow-400 px-4 py-3 text-sm font-semibold text-black shadow-md shadow-yellow-400/30 transition hover:bg-yellow-300 sm:w-auto"
        >
            Обрати план підписки
        </a>
    </div>

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
            @php($isHighlightedSubscription = (int) ($highlightSubscriptionId ?? 0) === (int) $subscription->id)
            <article class="rounded-2xl border border-gray-800 bg-gray-900 p-4 {{ $isHighlightedSubscription ? 'ring-2 ring-yellow-400/80 border-yellow-400/70' : '' }}" @if($isHighlightedSubscription) x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'center' }))" @endif>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-base font-semibold">{{ $subscription->plan?->name ?? 'План підписки' }}</p>
                        <p class="text-sm text-gray-400">{{ $subscription->address?->address_text ?? 'Адреса буде додана під час оформлення' }}</p>
                    </div>
                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $subscription->status_badge_classes }}">
                        {{ $subscription->ui_status_label }}
                    </span>
                </div>
                <div class="mt-2 space-y-1 text-xs text-gray-400">
                    <p>Пакет №{{ $subscription->latestPaidCheckoutOrder?->id ?? '—' }}</p>
                    <p>Оплачено/Створено: {{ $subscription->latestPaidCheckoutOrder?->created_at?->format('d.m.Y') ?? '—' }}</p>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-3 text-sm text-gray-300">
                    <p>План: <span class="text-white">{{ $subscription->frequency_label }}</span></p>
                    <p>Всього виносів: <span class="text-white">{{ max(1, (int) ($subscription->plan?->pickups_per_month ?? 1)) }}</span></p>
                    <p>Виконано: <span class="text-white">{{ $subscription->generatedOrders->where('origin', \App\Models\Order::ORIGIN_SUBSCRIPTION)->where('status', \App\Models\Order::STATUS_DONE)->count() }}</span></p>
                    <p>Залишилось: <span class="text-white">{{ max(0, max(1, (int) ($subscription->plan?->pickups_per_month ?? 1)) - $subscription->generatedOrders->where('origin', \App\Models\Order::ORIGIN_SUBSCRIPTION)->where('status', \App\Models\Order::STATUS_DONE)->count()) }}</span></p>
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

                <div class="mt-4 flex flex-wrap items-center gap-2 md:flex-nowrap">
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
                        @if($this->shouldShowResumeButton($subscription) && $subscription->canRenew())
                            <form method="POST" action="{{ route('client.subscriptions.renew', $subscription) }}">
                                @csrf
                                <button type="submit" class="rounded-xl bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-400">Продовжити</button>
                            </form>
                        @endif
                        @if($subscription->canOpenDetails())
                            <button wire:click="openDetails({{ $subscription->id }})" type="button" class="inline-flex items-center gap-1 rounded-xl border border-yellow-400/50 px-4 py-2 text-sm text-yellow-200 transition hover:border-yellow-300 hover:bg-yellow-300/10">Докладніше <span aria-hidden="true">→</span></button>
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
                            <button wire:click="openDetails({{ $subscription->id }})" type="button" class="inline-flex items-center gap-1 rounded-xl border border-yellow-400/50 px-4 py-2 text-sm text-yellow-200 transition hover:border-yellow-300 hover:bg-yellow-300/10">Докладніше <span aria-hidden="true">→</span></button>
                        @endif
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-700 bg-gray-900/70 p-4 text-sm text-gray-300">
                {{ $tab === 'archive'
                    ? 'Архів підписок порожній.'
                    : 'Підписок поки немає. Оформіть першу підписку під час створення замовлення.' }}

                @if($tab === 'active')
                    <a
                        href="{{ route('client.order.create', ['open_subscription_plans' => 1]) }}"
                        class="mt-3 inline-flex items-center justify-center rounded-xl bg-yellow-400 px-4 py-2 text-sm font-semibold text-black transition hover:bg-yellow-300"
                    >
                        Оформити підписку
                    </a>
                @endif
            </div>
        @endforelse
    </section>

    <x-poof.modal wire:model="showDetailsModal" maxWidth="max-w-none">
        <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-start px-2 py-2 sm:items-center sm:px-4 sm:py-8">
        <div class="box-border my-2 max-h-[calc(100vh-1rem)] w-[calc(100vw-1rem)] max-w-[calc(100vw-1rem)] overflow-y-auto rounded-2xl border border-gray-700/90 bg-gradient-to-b from-gray-900 via-gray-900 to-gray-950 shadow-xl sm:my-8 sm:max-h-[calc(100vh-4rem)] sm:w-full sm:max-w-2xl sm:rounded-3xl">
            <div class="sticky top-0 z-20 flex items-start justify-between gap-4 border-b border-gray-700/70 bg-gray-900/95 px-3 py-3 backdrop-blur sm:px-5">
                <div>
                    <h3 class="text-2xl font-bold text-white">Докладніше</h3>
                    <p class="text-sm text-gray-300">{{ $details['plan_name'] ?? 'План' }}</p>
                </div>
                <button wire:click="closeDetails" type="button" class="inline-flex h-10 w-10 shrink-0 items-center justify-center self-start rounded-lg text-3xl leading-none text-gray-300 transition hover:bg-gray-800 hover:text-white" aria-label="Закрити">×</button>
            </div>

            <div class="space-y-3 px-3 py-3 sm:px-5 sm:py-4">
            <div class="border-b border-gray-700/70 pb-3">
                <div class="grid grid-cols-1 gap-x-4 gap-y-2 text-sm text-gray-300 min-[390px]:grid-cols-2">
                    <p class="flex items-start gap-2"><span aria-hidden="true">📦</span><span>План / частота: <span class="font-semibold text-white">{{ ($details['plan_name'] ?? 'План') }} · {{ $details['frequency_label'] ?? '—' }}</span></span></p>
                    <p class="flex items-start gap-2"><span aria-hidden="true">🗓️</span><span>Період: <span class="text-white">{{ ($details['period_start'] ?? '—') }} — {{ ($details['period_end'] ?? '—') }}</span></span></p>
                    <p class="flex items-start gap-2"><span aria-hidden="true">●</span><span>Статус:
                        <span class="ml-1 inline-flex rounded-full border border-emerald-500/50 bg-emerald-500/20 px-2 py-0.5 text-xs font-semibold text-emerald-200">
                            {{ $details['status'] ?? '—' }}
                        </span>
                    </span></p>
                    <p class="flex items-start gap-2"><span aria-hidden="true">✅</span><span>Виконано: <span class="text-white">{{ $details['completed_runs'] ?? 0 }} з {{ $details['total_runs'] ?? 0 }}</span></span></p>
                    <p class="flex items-start gap-2"><span aria-hidden="true">↩</span><span>Залишилось: <span class="text-white">{{ $details['remaining_runs'] ?? 0 }}</span></span></p>
                    <p class="flex items-start gap-2"><span aria-hidden="true">⏭</span><span>Наступний винос: <span class="text-white">{{ $details['next_planned'] ?? '—' }}</span></span></p>
                    <p class="flex items-start gap-2"><span aria-hidden="true">♻</span><span>Автопродовження: <span class="text-white">{{ !empty($details['auto_renew']) ? 'Увімкнено' : 'Вимкнено' }}</span></span></p>
                </div>
            </div>

            <div class="border-b border-gray-700/70 pb-3">
                <p class="mb-2 text-xs uppercase tracking-wide text-gray-400">РОЗКЛАД ВИКОНАННЯ</p>
                <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-5 sm:gap-2">
                    @foreach(($details['timeline'] ?? []) as $run)
                        <div class="min-w-0 rounded-lg border border-gray-700/80 bg-gray-900/40 p-2 text-center">
                            <div class="mx-auto flex h-8 w-8 items-center justify-center rounded-full border {{ $run['completed'] ? 'border-yellow-300 bg-yellow-400 text-black' : 'border-gray-500 bg-transparent text-gray-400' }}">
                                @if($run['completed'])
                                    <span class="text-xs font-black text-emerald-700">✓</span>
                                @else
                                    <span class="text-[10px] font-semibold">{{ $run['index'] ?? '○' }}</span>
                                @endif
                            </div>
                            <div class="mt-1 text-xs font-bold text-gray-200">{{ $run['date'] }}</div>
                            <div class="mt-0.5 text-[10px] {{ $run['completed'] ? 'text-emerald-300' : 'text-gray-500' }}">{{ $run['completed'] ? 'Виконано' : 'Очікується' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500">ІСТОРІЯ ВИНОСІВ</p>
                <div class="mt-2 space-y-1.5">
                    @forelse(($details['history'] ?? []) as $executionOrder)
                        @php($isHighlightedExecutionOrder = (int) ($highlightOrderId ?? 0) === (int) ($executionOrder['id'] ?? 0))
                        <div class="min-w-0 rounded-lg p-2 text-xs text-gray-300 {{ ($executionOrder['awaiting_client_confirmation'] ?? false) ? 'border border-amber-400/60 bg-amber-500/10' : (($executionOrder['status'] ?? '') === 'Виконано' ? 'border border-emerald-500/60 bg-emerald-500/10' : 'border border-gray-700/80 bg-gray-900/30') }} {{ $isHighlightedExecutionOrder ? 'ring-2 ring-yellow-400/80' : '' }}" @if($isHighlightedExecutionOrder) id="highlighted-pending-confirmation-subscription-order" data-e2e="highlighted-pending-confirmation-subscription-order" x-init="$nextTick(() => document.getElementById('highlighted-pending-confirmation-subscription-order')?.scrollIntoView({ behavior: 'smooth', block: 'center' }))" @endif>
                            <div class="flex min-w-0 items-start justify-between gap-2">
                                <span class="inline-flex min-w-0 items-start gap-2 break-words font-semibold">
                                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border {{ ($executionOrder['status'] ?? '') === 'Виконано' ? 'border-emerald-400 bg-emerald-500/90 text-white' : 'border-gray-600 bg-gray-850 text-gray-300' }}">{{ ($executionOrder['status'] ?? '') === 'Виконано' ? '✓' : $executionOrder['execution_index'] }}</span>
                                    <span class="min-w-0 break-words">Винос {{ $executionOrder['execution_index'] }} із {{ $executionOrder['total_runs'] }} — замовлення №{{ $executionOrder['id'] }}</span>
                                </span>
                                <span class="shrink-0 text-right {{ ($executionOrder['awaiting_client_confirmation'] ?? false) ? 'text-amber-300' : (($executionOrder['status'] ?? '') === 'Виконано' ? 'text-emerald-300' : 'text-gray-400') }}">{{ ($executionOrder['awaiting_client_confirmation'] ?? false) ? 'Очікує підтвердження' : $executionOrder['status'] }}</span>
                            </div>
                            <div class="mt-1 text-[11px] text-gray-400">{{ $executionOrder['datetime'] }} · Курʼєр: {{ $executionOrder['courier_name'] ?? '—' }}</div>

                            @if($executionOrder['awaiting_client_confirmation'] ?? false)
                                @php($proofs = $executionOrder['completion_payload']['proofs'] ?? [])
                                @php($proofUrls = collect($proofs)->pluck('url')->filter()->values())
                                @php($deadlineAt = \Illuminate\Support\Carbon::parse($executionOrder['completion_payload']['completion_confirmation_deadline_at'] ?? $executionOrder['completion_payload']['auto_confirmation_due_at'] ?? null))
                                @php($serverNow = \Illuminate\Support\Carbon::parse($executionOrder['completion_payload']['server_now'] ?? now()))
                                @php($remainingSeconds = $deadlineAt ? max(0, $serverNow->diffInSeconds($deadlineAt, false)) : null)
                                <div class="mt-2 text-[11px] text-amber-200">Курʼєр завершив замовлення. Перевірте фото-звіт і підтвердьте виконання.</div>
                                @if(!is_null($remainingSeconds))
                                    <div class="mt-1 text-[11px] text-amber-300">
                                        @if($remainingSeconds > 0)
                                            Автопідтвердження через: {{ intdiv($remainingSeconds, 3600) }} год {{ intdiv($remainingSeconds % 3600, 60) }} хв
                                        @else
                                            Очікується автоматичне підтвердження
                                        @endif
                                    </div>
                                @endif
                                @if(!empty($proofs))
                                    <div class="mt-2" x-data="{ open: false, index: 0, proofs: @js($proofUrls), openAt(i){ this.index = i; this.open = true; }, prev(){ this.index = (this.index - 1 + this.proofs.length) % this.proofs.length; }, next(){ this.index = (this.index + 1) % this.proofs.length; } }" @keydown.escape.window="open = false" @keydown.arrow-left.window="if(open && proofs.length > 1) prev()" @keydown.arrow-right.window="if(open && proofs.length > 1) next()">
                                        <p class="text-[11px] text-gray-500">Фотозвіт</p>
                                        <div class="mt-1 grid grid-cols-4 gap-1">
                                            @foreach($proofUrls as $proofIndex => $proofUrl)
                                                <button type="button" @click="openAt({{ $proofIndex }})">
                                                    <img src="{{ $proofUrl }}" alt="Фото-звіт курʼєра, фото {{ $proofIndex + 1 }}" class="h-14 w-full rounded object-cover" />
                                                </button>
                                            @endforeach
                                        </div>
                                        <template x-if="open && proofs.length">
                                            <div class="fixed inset-0 z-[120] bg-black/85" @click.self="open = false" role="dialog" aria-modal="true" aria-label="Фото-звіт курʼєра">
                                                <div class="flex h-full w-full flex-col p-4 sm:p-6">
                                                    <div class="mb-3 flex items-center justify-between text-white">
                                                        <div>
                                                            <p class="text-sm font-semibold">Фото-звіт курʼєра</p>
                                                            <p class="text-xs text-gray-300" x-text="`Фото ${index + 1} з ${proofs.length}`"></p>
                                                        </div>
                                                        <button type="button" class="rounded border border-white/30 px-3 py-1 text-sm" aria-label="Закрити" @click="open = false">Закрити ×</button>
                                                    </div>
                                                    <div class="relative flex flex-1 items-center justify-center">
                                                        <img :src="proofs[index]" :alt="`Фото-звіт курʼєра, фото ${index + 1}`" class="max-h-[85vh] w-auto max-w-full rounded-lg object-contain" />
                                                        <template x-if="proofs.length > 1">
                                                            <div>
                                                                <button type="button" class="absolute left-1 top-1/2 -translate-y-1/2 rounded-full bg-black/60 px-3 py-2 text-white" @click="prev()">‹</button>
                                                                <button type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded-full bg-black/60 px-3 py-2 text-white" @click="next()">›</button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                @endif

                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @if($proofUrls->isNotEmpty())
                                        <span class="rounded-lg border border-gray-700 px-2 py-1 text-[11px] text-gray-200">Натисніть на фото для перегляду</span>
                                    @else
                                        <span class="rounded-lg border border-gray-800 px-2 py-1 text-[11px] text-gray-500">Фотозвіт відсутній</span>
                                    @endif
                                    <button wire:click="confirmExecutionCompletion({{ $detailsSubscriptionId }}, {{ $executionOrder['id'] }})" type="button" class="rounded-lg bg-yellow-400 px-2 py-1 text-[11px] font-semibold text-black">
                                        Підтвердити
                                    </button>
                                    <button wire:click="disputeExecutionCompletion({{ $detailsSubscriptionId }}, {{ $executionOrder['id'] }})" type="button" class="rounded-lg border border-red-500/40 px-2 py-1 text-[11px] text-red-200">
                                        Відкрити спір
                                    </button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-gray-500">Виноси ще не створені.</p>
                    @endforelse
                    @php($plannedRuns = $details['total_runs'] ?? 0)
                    @php($createdRuns = count($details['history'] ?? []))
                    @for($i = $createdRuns + 1; $i <= $plannedRuns; $i++)
                        <div class="min-w-0 rounded-lg border border-dashed border-gray-700 bg-gray-900/20 p-2 text-xs text-gray-400">
                            <span class="inline-flex w-full min-w-0 items-start justify-between gap-2">
                                <span class="inline-flex min-w-0 items-start gap-2 break-words">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-gray-600 text-[10px]">{{ $i }}</span>
                                <span class="min-w-0 break-words">Винос {{ $i }} із {{ $plannedRuns }} — заплановано, замовлення ще не створено</span>
                                </span>
                                <span aria-hidden="true" class="shrink-0 text-gray-500">›</span>
                            </span>
                        </div>
                    @endfor
                </div>
            </div>
            </div>
        </div>
        </div>
        </div>
    </x-poof.modal>
</div>
