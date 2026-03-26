<x-layouts.app title="POOF — Курʼєр">

<div x-data="{ logoutOpen: false, settingsOpen: false }" class="min-h-dvh bg-[#05070b] text-white flex justify-center">
    <div class="relative w-full max-w-md min-h-dvh flex flex-col bg-[#070a10]">

        <header class="sticky top-0 z-40 border-b border-white/10 bg-[#0b1119]/95 backdrop-blur">
            <div class="flex items-center justify-between gap-3 px-4 py-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-100">POOF Courier</p>
                </div>

                <livewire:courier.online-toggle wire:key="courier-online-toggle-header" />
            </div>
        </header>

        <main class="flex-1 overflow-y-auto pb-28">
            {{ $slot }}
        </main>

        <nav class="fixed bottom-0 left-1/2 z-50 w-full max-w-md -translate-x-1/2 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            <div class="rounded-2xl border border-white/10 bg-[#0c131d]/95 p-1.5 shadow-[0_-8px_30px_rgba(0,0,0,0.45)] backdrop-blur-md">
                <div class="grid grid-cols-3 gap-1 text-[11px] font-medium">
                    <a
                        href="{{ route('courier.orders') }}"
                        wire:navigate
                        class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl transition {{ request()->routeIs('courier.orders') ? 'bg-poof/20 text-poof' : 'text-slate-400 hover:text-white' }}"
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
                        class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl transition {{ request()->routeIs('courier.my-orders') ? 'bg-poof/20 text-poof' : 'text-slate-400 hover:text-white' }}"
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
                        class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-slate-400 transition hover:text-white"
                        @click="settingsOpen = true"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <path d="m16 17 5-5-5-5" />
                            <path d="M21 12H9" />
                        </svg>
                        <span>Профіль</span>
                    </button>
                </div>
            </div>
        </nav>

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

        <div x-show="settingsOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[998] flex items-center justify-center bg-black/75 px-4">
            <div @click.away="settingsOpen = false" class="w-80 rounded-3xl border border-white/10 bg-[#0d121b] p-6 shadow-2xl">
                <div class="mb-4 text-center text-lg font-semibold">Профіль</div>
                <div class="space-y-3">
                    <button @click="logoutOpen = true; settingsOpen = false" class="w-full rounded-2xl bg-rose-500 py-3 text-sm font-semibold text-white transition hover:bg-rose-600">Вийти з акаунту</button>
                    <button @click="settingsOpen = false" class="w-full rounded-2xl border border-white/10 bg-white/5 py-3 transition hover:bg-white/10">Закрити</button>
                </div>
            </div>
        </div>

        <div class="h">
            <livewire:courier.location-tracker />
            <livewire:courier.offer-card />
        </div>
    </div>
</div>

</x-layouts.app>
