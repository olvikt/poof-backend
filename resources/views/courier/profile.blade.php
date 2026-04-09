@php
    $courierIdentityLabel = $courier->courierProfile?->id
        ? 'id: '.$courier->courierProfile->id
        : 'id: '.$courier->id;
@endphp

@component('layouts.courier', ['headerMeta' => $courierIdentityLabel])
<div class="min-h-screen bg-[#070a10] px-4 pb-24 pt-4 text-white">
    <section class="relative rounded-3xl border border-white/10 bg-[#0d1724] p-4 shadow-[0_12px_36px_rgba(0,0,0,0.45)]">
        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('sheet:open',{detail:{name:'courierEditProfile'}}))"
            aria-label="Редагувати профіль"
            class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/15 bg-white/5 text-slate-100 transition hover:bg-white/10"
        >
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4 w-4">
                <path d="M4 20h4l10-10-4-4L4 16v4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                <path d="m12 6 4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            </svg>
        </button>
        <div class="flex items-start gap-4">
            <div class="shrink-0">
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('sheet:open',{detail:{name:'courierEditAvatar'}}))"
                    aria-label="Оновити аватар"
                    class="group relative overflow-hidden rounded-2xl border border-white/15"
                >
                    <img
                        x-data="{ src: '{{ $profile['profile_media']['avatar_url'] }}' }"
                        x-on:avatar-saved.window="
                            if ($event.detail?.avatarUrl) {
                                src = `${$event.detail.avatarUrl}?${Date.now()}`;
                            }
                        "
                        :src="src"
                        alt="avatar"
                        class="h-20 w-20 object-cover"
                    />
                    <span class="pointer-events-none absolute inset-0 flex items-center justify-center bg-[#041015]/70 text-[11px] font-semibold opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100">
                        Змінити фото
                    </span>
                </button>
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-lg font-semibold">{{ $profile['profile_identity']['full_name'] }}</div>
                <p class="text-xs text-slate-300">{{ $profile['profile_contact']['phone'] }}</p>
                @php
                    $isVerified = ($profile['profile_verification']['status'] ?? null) === 'verified';
                    $verificationBadgeClasses = $isVerified
                        ? 'border-emerald-400/40 bg-emerald-400/15 text-emerald-300'
                        : 'border-poof/40 bg-poof/20 text-poof';
                @endphp
                <div class="mt-2 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $verificationBadgeClasses }}">
                    @if($isVerified)
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true" class="h-3.5 w-3.5" data-e2e="courier-verified-icon">
                            <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.5"/>
                            <path d="m6.8 10.2 2.1 2.1 4.3-4.4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    @endif
                    <span>{{ $profile['profile_verification']['status_label'] }}</span>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-4 grid grid-cols-2 gap-3">
        <article class="relative rounded-2xl border border-white/10 bg-[#0d1724] p-4">
            <div class="text-xs uppercase tracking-wide text-slate-400">Рейтинг</div>
            <button
                type="button"
                aria-label="Деталі рейтингу"
                onclick="window.dispatchEvent(new CustomEvent('sheet:open',{detail:{name:'courierRatingDetails'}}))"
                class="absolute right-3 top-3 inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#facc15] text-[#1f2937]"
            >
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true" class="h-3.5 w-3.5">
                    <path d="M7.2 14.3 12.8 10 7.2 5.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <div class="mt-2 text-xl font-black">{{ number_format((float) $profile['rating_summary']['current_score'], 2) }}/5</div>
        </article>

        <article class="rounded-2xl border border-white/10 bg-[#0d1724] p-4">
            <div class="text-xs uppercase tracking-wide text-slate-400">Гаманець</div>
            <div class="mt-2 text-xl font-bold">{{ number_format((int) $profile['balance_summary']['available_to_withdraw'], 2, ',', ' ') }} ₴</div>
        </article>
    </section>

    <section class="mt-4 rounded-2xl border border-white/10 bg-[#0d1724] p-4">
        <h2 class="text-sm font-semibold">Акаунт</h2>
        <div class="mt-3 grid grid-cols-1 gap-2">
            <a href="{{ route('courier.wallet') }}" class="flex items-center justify-between rounded-xl border border-white/10 bg-[#101b2b] px-3 py-2.5 text-sm font-medium">
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
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" class="h-4 w-4 text-rose-200">
                        <path d="M13 5h6v14h-6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M10 8 5 12l5 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M5 12h10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    </svg>
                </button>
            </form>
        </div>
    </section>

    <section class="mt-4 rounded-2xl border border-white/10 bg-[#0d1724] p-4">
        <h2 class="text-sm font-semibold">Налаштування та верифікація</h2>
        <div class="mt-3 rounded-xl border border-white/10 bg-[#101b2b] p-3">
            <div class="text-xs uppercase tracking-wide text-slate-400">Верифікація</div>
            <p class="mt-1 text-sm">Поточний статус: {{ $profile['profile_verification']['status_label'] }}</p>
            <p class="mt-2 text-xs text-slate-400">{{ $profile['profile_verification']['description'] }}</p>
            @if($profile['profile_verification']['show_rejection_reason'])
                <p class="mt-2 text-xs text-rose-300">Причина: {{ $profile['profile_verification']['rejection_reason'] }}</p>
            @endif
            @if($profile['profile_verification']['can_submit'])
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('sheet:open',{detail:{name:'courierVerificationUpload'}}))"
                    class="mt-3 rounded-xl bg-poof px-3 py-2 text-xs font-bold text-[#041015]"
                >
                    {{ $profile['profile_verification']['cta_label'] }}
                </button>
            @endif
        </div>
        <div class="mt-3 rounded-xl border border-white/10 bg-[#101b2b] p-3">
            <div class="text-xs uppercase tracking-wide text-slate-400">Адреса</div>
            <p class="mt-1 text-sm">{{ $profile['profile_address']['residence_address'] }}</p>
        </div>
    </section>

    <x-poof.ui.bottom-sheet name="courierEditProfile" title="Профіль курʼєра">
        <form method="POST" action="{{ route('courier.profile.update') }}" class="space-y-3">
            @csrf
            <input name="name" value="{{ old('name', $courier->name) }}" class="poof-input w-full" placeholder="ПІБ" required>
            <input name="phone" value="{{ old('phone', $courier->phone) }}" class="poof-input w-full" placeholder="Телефон" required>
            <input type="email" name="email" value="{{ old('email', $courier->email) }}" class="poof-input w-full" placeholder="Email" required>
            <select name="residence_city" class="poof-input w-full" required>
                @foreach($cityOptions as $cityOption)
                    <option value="{{ $cityOption }}" @selected(old('residence_city', $residenceCity) === $cityOption)>{{ $cityOption }}</option>
                @endforeach
            </select>
            <textarea name="residence_address_line" class="poof-input w-full" placeholder="Адреса" required>{{ old('residence_address_line', $residenceAddressLine) }}</textarea>
            <button class="w-full rounded-xl bg-poof py-3 text-sm font-bold text-[#041015]">Зберегти</button>
        </form>
    </x-poof.ui.bottom-sheet>

    <x-poof.ui.bottom-sheet name="courierEditAvatar" title="Аватар курʼєра">
        <livewire:courier.avatar-form />
    </x-poof.ui.bottom-sheet>

    <x-poof.ui.bottom-sheet name="courierRatingDetails" title="Деталі рейтингу">
        <div class="space-y-3 text-sm">
            <p><span class="text-slate-400">Поточний рейтинг:</span> <strong>{{ number_format((float) $profile['rating_summary']['current_score'], 2) }}/5</strong></p>
            <p class="text-xs text-slate-400">Контракт {{ $profile['rating_summary']['phase'] }} (provisional).</p>
            @foreach($profile['rating_summary']['factors'] as $factor)
                <div class="rounded-lg border border-white/10 bg-[#101b2b] p-2.5">
                    <p class="font-semibold text-white">{{ $factor['label'] }}</p>
                    <p class="text-xs text-slate-300">Значення: {{ $factor['value'] }} · Вага: {{ (int) ($factor['weight'] * 100) }}%</p>
                </div>
            @endforeach
            <div>
                <p class="font-semibold text-white">Що покращує рейтинг:</p>
                <ul class="list-disc pl-5 text-xs text-slate-300">
                    @foreach($profile['rating_summary']['explainability']['improves'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
            <div>
                <p class="font-semibold text-white">Що знижує рейтинг:</p>
                <ul class="list-disc pl-5 text-xs text-slate-300">
                    @foreach($profile['rating_summary']['explainability']['lowers'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </x-poof.ui.bottom-sheet>

    <x-poof.ui.bottom-sheet name="courierVerificationUpload" title="Верифікація курʼєра">
        <form method="POST" action="{{ route('courier.profile.verification.submit') }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <select name="document_type" class="poof-input w-full" required>
                <option value="passport">Паспорт</option>
                <option value="id_card">ID-картка</option>
            </select>
            <input type="file" name="document" class="poof-input w-full" accept="image/jpeg,image/png,image/webp" required>
            <p class="text-xs text-slate-400">Підтримувані формати: JPG, PNG, WEBP. Максимум 5MB.</p>
            <button class="w-full rounded-xl bg-poof py-3 text-sm font-bold text-[#041015]">Надіслати на перевірку</button>
        </form>
    </x-poof.ui.bottom-sheet>

</div>
@endcomponent
