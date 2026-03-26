<x-layouts.app title="POOF — Курʼєр">

<div x-data="{ logoutOpen: false, settingsOpen: false }" class="min-h-dvh bg-[#07090d] text-white flex justify-center">

    <div class="relative w-full max-w-md min-h-dvh flex flex-col bg-[#090c12]">

        {{-- ================= HEADER ================= --}}
        <header class="sticky top-0 z-40 border-b border-white/10 bg-[#0c1119]/95 backdrop-blur-sm">
            <div class="px-4 py-3">
                <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.03] px-3 py-2.5 shadow-[0_10px_30px_rgba(0,0,0,0.35)]">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-poof/20 text-poof">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 17a4 4 0 1 1 0-8h1" />
                            <path d="M8 13h6" />
                            <circle cx="18" cy="17" r="2" />
                            <path d="M14 17h2" />
                            <path d="M13 9l2 4" />
                        </svg>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="text-[10px] uppercase tracking-[0.2em] text-slate-400">Courier cabinet</div>
                        <div class="truncate text-sm font-semibold tracking-wide">
                            <span class="text-poof">POOF</span>
                            <span class="ml-1 text-slate-300">Курʼєр</span>
                        </div>
                    </div>

                    <livewire:courier.online-toggle wire:key="courier-online-toggle-header" />
                </div>
            </div>
        </header>

        {{-- ================= CONTENT ================= --}}
        <main class="flex-1 overflow-y-auto px-4 py-4 pb-32">
            {{ $slot }}
        </main>

        {{-- ================= BOTTOM NAV ================= --}}
        <nav class="fixed bottom-0 left-1/2 z-50 w-full max-w-md -translate-x-1/2 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            <div class="rounded-3xl border border-white/10 bg-[#0d131d]/95 p-1.5 shadow-[0_-8px_30px_rgba(0,0,0,0.45)] backdrop-blur-md">
                <div class="grid grid-cols-4 gap-1 text-[11px] font-medium">

                    <a
                        href="{{ route('courier.orders') }}"
                        wire:navigate
                        class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-2xl transition {{ request()->routeIs('courier.orders') ? 'bg-poof/15 text-poof' : 'text-slate-400 hover:text-white' }}"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 7.5 12 3l9 4.5-9 4.5L3 7.5Z" />
                            <path d="M3 12.5 12 17l9-4.5" />
                            <path d="M3 17l9 4 9-4" />
                        </svg>
                        <span>Доступні</span>
                    </a>

                    <a
                        href="{{ route('courier.my-orders') }}"
                        wire:navigate
                        class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-2xl transition {{ request()->routeIs('courier.my-orders') ? 'bg-poof/15 text-poof' : 'text-slate-400 hover:text-white' }}"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="4" y="3" width="16" height="18" rx="2" />
                            <path d="M8 7h8" />
                            <path d="M8 11h8" />
                            <path d="M8 15h5" />
                        </svg>
                        <span>Мої</span>
                    </a>

                    <button
                        type="button"
                        class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-2xl text-slate-400 transition hover:text-white"
                        @click="settingsOpen = true"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="3" />
                            <path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a2 2 0 1 1-4 0v-.2a1 1 0 0 0-.6-.9 1 1 0 0 0-1.1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a2 2 0 1 1 0-4h.2a1 1 0 0 0 .9-.6 1 1 0 0 0-.2-1.1l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1 1 0 0 0 1.1.2h.1a1 1 0 0 0 .6-.9V4a2 2 0 1 1 4 0v.2a1 1 0 0 0 .6.9h.1a1 1 0 0 0 1.1-.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1 1 0 0 0-.2 1.1v.1a1 1 0 0 0 .9.6H20a2 2 0 1 1 0 4h-.2a1 1 0 0 0-.9.6V15Z"/>
                        </svg>
                        <span>Налашт.</span>
                    </button>

                    <button
                        type="button"
                        class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-2xl text-slate-400 transition hover:text-rose-300"
                        @click="logoutOpen = true"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <path d="m16 17 5-5-5-5" />
                            <path d="M21 12H9" />
                        </svg>
                        <span>Вийти</span>
                    </button>

                </div>
            </div>
        </nav>

        {{-- ================= LOGOUT MODAL ================= --}}
        <div x-show="logoutOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[999] flex items-center justify-center bg-black/75 px-4">
            <div @click.away="logoutOpen = false" class="w-80 rounded-3xl border border-white/10 bg-[#0d121b] p-6 text-center shadow-2xl">
                <div class="mb-4 text-lg font-semibold">Вийти з акаунту?</div>

                <div class="flex gap-3">
                    <button @click="logoutOpen = false" class="flex-1 rounded-2xl border border-white/10 bg-white/5 py-3 transition hover:bg-white/10">
                        Скасувати
                    </button>

                    <form method="POST" action="{{ route('logout') }}" class="flex-1">
                        @csrf
                        <button type="submit" class="w-full rounded-2xl bg-rose-500 py-3 text-center font-bold text-white transition hover:bg-rose-600">
                            Вийти
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- SETTINGS MODAL --}}
        <div x-show="settingsOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[998] flex items-center justify-center bg-black/75 px-4">
            <div @click.away="settingsOpen = false" class="w-80 rounded-3xl border border-white/10 bg-[#0d121b] p-6 shadow-2xl">
                <div class="mb-4 text-center text-lg font-semibold">Налаштування</div>

                <div class="space-y-3 text-sm">
                    <a href="#" class="block rounded-2xl border border-white/10 bg-white/5 px-4 py-3 transition hover:bg-white/10">Кошелёк</a>
                    <a href="#" class="block rounded-2xl border border-white/10 bg-white/5 px-4 py-3 transition hover:bg-white/10">Профіль</a>
                    <a href="#" class="block rounded-2xl border border-white/10 bg-white/5 px-4 py-3 transition hover:bg-white/10">Акції</a>

                    <button @click="settingsOpen = false" class="mt-3 w-full rounded-2xl border border-white/10 bg-white/5 py-3 transition hover:bg-white/10">
                        Закрити
                    </button>
                </div>
            </div>
        </div>

        {{-- BACKGROUND SERVICES --}}
        <div class="h">
            <livewire:courier.location-tracker />
            <livewire:courier.offer-card />
        </div>

    </div>

</div>

</x-layouts.app>
