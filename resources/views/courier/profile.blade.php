@component('layouts.courier')
<div class="min-h-screen bg-[#070a10] px-4 pb-24 pt-4 text-white">
    <section class="rounded-3xl border border-white/10 bg-[#0d1724] p-4 shadow-[0_12px_36px_rgba(0,0,0,0.45)]">
        <div class="flex items-start gap-4">
            <div class="shrink-0">
                <img src="{{ $profile['profile_media']['avatar_url'] }}" alt="avatar" class="h-20 w-20 rounded-2xl object-cover" />
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('sheet:open',{detail:{name:'courierEditAvatar'}}))" class="mt-2 w-full rounded-xl border border-white/15 bg-white/10 px-2 py-1.5 text-xs font-semibold">Змінити</button>
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-lg font-semibold">{{ $profile['profile_identity']['full_name'] }}</div>
                <p class="mt-1 text-sm text-slate-300">{{ $profile['profile_contact']['phone'] }}</p>
                <p class="text-sm text-slate-300">{{ $profile['profile_contact']['email'] }}</p>
                <div class="mt-2 inline-flex rounded-full border border-poof/40 bg-poof/20 px-2.5 py-1 text-[11px] font-semibold text-poof">
                    Статус: {{ $profile['profile_verification']['status'] }}
                </div>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('sheet:open',{detail:{name:'courierEditProfile'}}))" class="mt-3 rounded-xl bg-poof px-3 py-2 text-xs font-bold text-[#041015]">Редагувати профіль</button>
            </div>
        </div>
    </section>

    <section class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <article class="rounded-2xl border border-white/10 bg-[#0d1724] p-4">
            <div class="text-xs uppercase tracking-wide text-slate-400">Рейтинг</div>
            <div class="mt-2 text-3xl font-black">{{ number_format((float) $profile['rating_summary']['current_score'], 2) }}/5</div>
            <p class="mt-2 text-xs text-slate-300">{{ $profile['rating_summary']['summary'] }}</p>
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('sheet:open',{detail:{name:'courierRatingDetails'}}))" class="mt-3 rounded-lg border border-white/20 px-2.5 py-1.5 text-xs">Докладніше</button>
        </article>

        <article id="courier-balance-block" class="rounded-2xl border border-white/10 bg-[#0d1724] p-4">
            <div class="text-xs uppercase tracking-wide text-slate-400">Фінанси</div>
            <div class="mt-2 text-sm text-slate-300">Загалом зароблено</div>
            <div class="text-xl font-bold">{{ number_format((int) $profile['balance_summary']['earned_total'], 2, ',', ' ') }} ₴</div>
            <div class="mt-2 text-sm text-slate-300">Доступно до виводу</div>
            <div class="text-xl font-bold">{{ number_format((int) $profile['balance_summary']['available_to_withdraw'], 2, ',', ' ') }} ₴</div>
            <div class="mt-1 text-xs text-slate-400">Мінімальний вивід: {{ number_format((int) $profile['balance_summary']['min_withdrawal_amount'], 2, ',', ' ') }} ₴</div>
            <button
                type="button"
                onclick="window.dispatchEvent(new CustomEvent('sheet:open',{detail:{name:'courierWithdrawal'}}))"
                @disabled(! $profile['balance_summary']['can_request_withdrawal'])
                class="mt-3 rounded-xl px-3 py-2 text-xs font-bold {{ $profile['balance_summary']['can_request_withdrawal'] ? 'bg-poof text-[#041015]' : 'cursor-not-allowed bg-white/10 text-slate-500' }}"
            >
                Запросити вивід
            </button>
        </article>
    </section>

    <section class="mt-4 rounded-2xl border border-white/10 bg-[#0d1724] p-4">
        <h2 class="text-sm font-semibold">Налаштування та верифікація</h2>
        <div class="mt-3 rounded-xl border border-white/10 bg-[#101b2b] p-3">
            <div class="text-xs uppercase tracking-wide text-slate-400">Верифікація</div>
            <p class="mt-1 text-sm">Поточний статус: {{ $profile['profile_verification']['status'] }}</p>
            <p class="mt-2 text-xs text-slate-400">{{ $profile['profile_verification']['kyc_placeholder'] }}</p>
        </div>
        <div class="mt-3 rounded-xl border border-white/10 bg-[#101b2b] p-3">
            <div class="text-xs uppercase tracking-wide text-slate-400">Адреса ПМЖ</div>
            <p class="mt-1 text-sm">{{ $profile['profile_address']['residence_address'] }}</p>
        </div>
    </section>

    <section class="mt-4 rounded-2xl border border-white/10 bg-[#0d1724] p-4">
        <h2 class="text-sm font-semibold">Акаунт</h2>
        <div class="mt-3 grid grid-cols-1 gap-2">
            <a href="#courier-balance-block" class="flex items-center justify-between rounded-xl border border-white/10 bg-[#101b2b] px-3 py-2.5 text-sm font-medium">
                <span>Гаманець / Баланс</span>
                <span class="text-xs text-slate-400">Перейти</span>
            </a>
            <a href="https://t.me/poofsupport" target="_blank" rel="noopener noreferrer" class="flex items-center justify-between rounded-xl border border-white/10 bg-[#101b2b] px-3 py-2.5 text-sm font-medium">
                <span>Підтримка</span>
                <span class="text-xs text-slate-400">Telegram</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center justify-between rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-2.5 text-left text-sm font-semibold text-rose-100">
                    <span>Вийти з акаунту</span>
                    <span class="text-xs text-rose-200">Logout</span>
                </button>
            </form>
        </div>
    </section>

    <x-poof.ui.bottom-sheet name="courierEditProfile" title="Профіль курʼєра">
        <form method="POST" action="{{ route('courier.profile.update') }}" class="space-y-3">
            @csrf
            <input name="name" value="{{ old('name', $courier->name) }}" class="poof-input w-full" placeholder="ПІБ" required>
            <input name="phone" value="{{ old('phone', $courier->phone) }}" class="poof-input w-full" placeholder="Телефон" required>
            <input type="email" name="email" value="{{ old('email', $courier->email) }}" class="poof-input w-full" placeholder="Email" required>
            <textarea name="residence_address" class="poof-input w-full" placeholder="Адреса ПМЖ" required>{{ old('residence_address', $courier->residence_address) }}</textarea>
            <button class="w-full rounded-xl bg-poof py-3 text-sm font-bold text-[#041015]">Зберегти</button>
        </form>
    </x-poof.ui.bottom-sheet>

    <x-poof.ui.bottom-sheet name="courierEditAvatar" title="Аватар курʼєра">
        <form method="POST" action="{{ route('courier.profile.avatar.update') }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <input type="file" name="avatar" accept="image/*" class="poof-input w-full" required>
            <button class="w-full rounded-xl bg-poof py-3 text-sm font-bold text-[#041015]">Оновити аватар</button>
        </form>
    </x-poof.ui.bottom-sheet>

    <x-poof.ui.bottom-sheet name="courierRatingDetails" title="Деталі рейтингу">
        <div class="space-y-3 text-sm">
            <p><span class="text-slate-400">Поточний рейтинг:</span> <strong>{{ number_format((float) $profile['rating_summary']['current_score'], 2) }}/5</strong></p>
            <p class="text-xs text-slate-400">Контракт {{ $profile['rating_summary']['phase'] }} (provisional).</p>
            @foreach($profile['rating_summary']['factors'] as $factor)
                <div class="rounded-lg border border-white/10 bg-[#101b2b] p-2.5">
                    <p class="font-semibold">{{ $factor['label'] }}</p>
                    <p class="text-xs text-slate-300">Значення: {{ $factor['value'] }} · Вага: {{ (int) ($factor['weight'] * 100) }}%</p>
                </div>
            @endforeach
            <div>
                <p class="font-semibold">Що покращує рейтинг:</p>
                <ul class="list-disc pl-5 text-xs text-slate-300">
                    @foreach($profile['rating_summary']['explainability']['improves'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
            <div>
                <p class="font-semibold">Що знижує рейтинг:</p>
                <ul class="list-disc pl-5 text-xs text-slate-300">
                    @foreach($profile['rating_summary']['explainability']['lowers'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </x-poof.ui.bottom-sheet>

    <x-poof.ui.bottom-sheet name="courierWithdrawal" title="Запросити вивід">
        <form method="POST" action="{{ route('courier.profile.withdrawal.request') }}" class="space-y-3">
            @csrf
            <input type="number" min="1" name="amount" class="poof-input w-full" placeholder="Сума" required>
            <textarea name="notes" class="poof-input w-full" placeholder="Коментар (опційно)"></textarea>
            <button class="w-full rounded-xl bg-poof py-3 text-sm font-bold text-[#041015]" @disabled(! $profile['balance_summary']['can_request_withdrawal'])>Надіслати запит</button>
            @if($profile['balance_summary']['withdrawal_block_reason'])
                <p class="text-xs text-amber-300">Запит заблоковано: {{ $profile['balance_summary']['withdrawal_block_reason'] }}.</p>
            @endif
        </form>
    </x-poof.ui.bottom-sheet>

</div>
@endcomponent
