<x-layouts.app title="POOF — Курʼєр">

<div class="min-h-dvh bg-[#05070b] text-white flex justify-center">
    <div class="relative w-full max-w-md min-h-dvh flex flex-col bg-[#070a10] [--courier-header-h:64px] [--courier-nav-h:92px] [--courier-screen-bottom-gap:0.75rem]">

        <header class="sticky top-0 z-40 border-b border-white/10 bg-[#0b121c] shadow-[0_12px_30px_rgba(0,0,0,0.52)]">
            <div class="flex items-center justify-between gap-2 px-4 py-2.5">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold tracking-[0.01em] text-slate-100">POOF Кур'єр</p>
                </div>

                <livewire:courier.online-toggle wire:key="courier-online-toggle-header" />
            </div>
        </header>

        <main class="flex-1 overflow-y-auto pb-[calc(var(--courier-nav-h)+env(safe-area-inset-bottom)+var(--courier-screen-bottom-gap))]">
            {{ $slot }}
        </main>

        <div class="pointer-events-none fixed bottom-[calc(4.9rem+env(safe-area-inset-bottom))] left-1/2 z-40 h-1 w-full max-w-md -translate-x-1/2 bg-gradient-to-t from-[#070a10]/[0.35] via-[#070a10]/[0.10] to-transparent"></div>

        <nav class="fixed bottom-0 left-1/2 z-50 w-full max-w-md -translate-x-1/2 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            <div class="rounded-2xl border border-white/[0.12] bg-[#0e1622] p-1 shadow-[0_-14px_34px_rgba(0,0,0,0.62)] ring-1 ring-black/35">
                <div class="grid grid-cols-3 gap-1 text-[11px] font-medium">
                    <a
                        href="{{ route('courier.orders') }}"
                        wire:navigate
                        class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl transition {{ request()->routeIs('courier.orders') ? 'bg-poof/22 text-poof shadow-[inset_0_0_0_1px_rgba(47,217,184,0.38)]' : 'text-slate-300 hover:bg-white/[0.06] hover:text-white' }}"
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
                        class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl transition {{ request()->routeIs('courier.my-orders') ? 'bg-poof/22 text-poof shadow-[inset_0_0_0_1px_rgba(47,217,184,0.38)]' : 'text-slate-300 hover:bg-white/[0.06] hover:text-white' }}"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="4" y="3" width="16" height="18" rx="2" />
                            <path d="M8 7h8" />
                            <path d="M8 11h8" />
                            <path d="M8 15h5" />
                        </svg>
                        <span>Мої</span>
                    </a>

                    <a
                        href="{{ route('courier.profile') }}"
                        wire:navigate
                        class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl transition {{ request()->routeIs('courier.profile') ? 'bg-poof/22 text-poof shadow-[inset_0_0_0_1px_rgba(47,217,184,0.38)]' : 'text-slate-300 hover:bg-white/[0.06] hover:text-white' }}"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21a8 8 0 1 0-16 0" />
                            <circle cx="12" cy="8" r="4" />
                        </svg>
                        <span>Профіль</span>
                    </a>
                </div>
            </div>
        </nav>

        <div class="h">
            <livewire:courier.location-tracker />
            <livewire:courier.offer-card />
        </div>
    </div>
</div>

</x-layouts.app>
