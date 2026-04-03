<div
    x-show="moreShellOpen"
    @keydown.escape.window="closeMoreShell()"
    x-transition.opacity
    class="fixed inset-0 z-[100]"
    style="display: none;"
>
    <div class="absolute inset-0 bg-black/70" @click="closeMoreShell()"></div>

    <div class="absolute inset-0 overflow-hidden bg-gray-950">
        <section
            class="absolute inset-0 flex flex-col bg-gray-950 transition-transform duration-300 ease-out"
            :style="`transform: ${transformFor('root')}`"
            x-show="moreStack.includes('root')"
            style="display: none;"
        >
            <header class="h-16 px-4 flex items-center justify-between border-b border-gray-800 bg-gray-900/95 backdrop-blur">
                <span class="text-sm text-gray-400">Меню</span>
                <span class="font-semibold text-white text-base">Більше</span>
                <button
                    type="button"
                    @click="closeMoreShell()"
                    class="w-10 h-10 rounded-full flex items-center justify-center bg-gray-800 hover:bg-gray-700 text-white transition"
                    aria-label="Закрити меню"
                >
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6L6 18"/>
                        <path d="M6 6l12 12"/>
                    </svg>
                </button>
            </header>

            <div class="flex-1 overflow-y-auto px-4 py-6">
                <nav class="text-sm text-gray-200">
                    <button type="button" @click="openMoreScreen('subscriptions')" class="flex w-full items-center gap-4 py-4 border-b border-gray-800/60 hover:bg-gray-800/60 transition group">
                        <span class="w-6 text-center">⭐</span>
                        <span class="flex-1 text-left">Підписка</span>
                        <span class="w-4 text-right text-gray-500">›</span>
                    </button>

                    <button type="button" @click="openMoreScreen('addresses')" class="flex w-full items-center gap-4 py-4 border-b border-gray-800/60 hover:bg-gray-800/60 transition group">
                        <span class="w-6 text-center">📍</span>
                        <span class="flex-1 text-left">Мої адреси</span>
                        <span class="w-4 text-right text-gray-500">›</span>
                    </button>

                    <button type="button" @click="openMoreScreen('billing')" class="flex w-full items-center gap-4 py-4 border-b border-gray-800/60 hover:bg-gray-800/60 transition group">
                        <span class="w-6 text-center">💳</span>
                        <span class="flex-1 text-left">Оплата</span>
                        <span class="w-4 text-right text-gray-500">›</span>
                    </button>

                    <button type="button" @click="openMoreScreen('promocodes')" class="flex w-full items-center gap-4 py-4 border-b border-gray-800/60 hover:bg-gray-800/60 transition group">
                        <span class="w-6 text-center">🎁</span>
                        <span class="flex-1 text-left">Промокоди</span>
                        <span class="w-4 text-right text-gray-500">›</span>
                    </button>

                    <a href="{{ route('client.support') }}" @click="closeMoreShell()" class="flex items-center gap-4 py-4 border-b border-gray-800/60 hover:bg-gray-800/60 transition group">
                        <span class="w-6 text-center">📞</span>
                        <span class="flex-1">Підтримка</span>
                        <span class="w-4 text-right text-gray-500">›</span>
                    </a>

                    <button type="button" @click="openMoreScreen('settings')" class="flex w-full items-center gap-4 py-4 hover:bg-gray-800/60 transition group">
                        <span class="w-6 text-center">⚙️</span>
                        <span class="flex-1 text-left">Налаштування</span>
                        <span class="w-4 text-right text-gray-500">›</span>
                    </button>
                </nav>
            </div>
        </section>

        <template x-if="moreStack.includes('subscriptions')">
            <section class="absolute inset-0 flex flex-col bg-gray-950 transition-transform duration-300 ease-out" :style="`transform: ${transformFor('subscriptions')}`">
                <header class="h-16 px-4 flex items-center justify-between border-b border-gray-800 bg-gray-900/95 backdrop-blur">
                    <button type="button" @click="backMoreScreen()" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-800 text-white hover:bg-gray-700" aria-label="Назад">←</button>
                    <span class="font-semibold text-white text-base">Підписка</span>
                    <button type="button" @click="closeMoreShell()" class="w-10 h-10 rounded-full flex items-center justify-center bg-gray-800 hover:bg-gray-700 text-white transition" aria-label="Закрити меню">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                    </button>
                </header>
                <div class="flex-1 overflow-y-auto px-4 py-6">
                    <livewire:client.subscriptions-page :embedded="true" lazy />
                </div>
            </section>
        </template>

        <template x-if="moreStack.includes('addresses')">
            <section class="absolute inset-0 flex flex-col bg-gray-950 transition-transform duration-300 ease-out" :style="`transform: ${transformFor('addresses')}`">
                <header class="h-16 px-4 flex items-center justify-between border-b border-gray-800 bg-gray-900/95 backdrop-blur">
                    <button type="button" @click="backMoreScreen()" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-800 text-white hover:bg-gray-700" aria-label="Назад">←</button>
                    <span class="font-semibold text-white text-base">Мої адреси</span>
                    <button type="button" @click="closeMoreShell()" class="w-10 h-10 rounded-full flex items-center justify-center bg-gray-800 hover:bg-gray-700 text-white transition" aria-label="Закрити меню">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                    </button>
                </header>
                <div class="flex-1 overflow-y-auto px-4 py-6">
                    <livewire:client.addresses-page :embedded="true" lazy />
                </div>
            </section>
        </template>

        <template x-if="moreStack.includes('billing')">
            <section class="absolute inset-0 flex flex-col bg-gray-950 transition-transform duration-300 ease-out" :style="`transform: ${transformFor('billing')}`">
                <header class="h-16 px-4 flex items-center justify-between border-b border-gray-800 bg-gray-900/95 backdrop-blur">
                    <button type="button" @click="backMoreScreen()" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-800 text-white hover:bg-gray-700" aria-label="Назад">←</button>
                    <span class="font-semibold text-white text-base">Оплата</span>
                    <button type="button" @click="closeMoreShell()" class="w-10 h-10 rounded-full flex items-center justify-center bg-gray-800 hover:bg-gray-700 text-white transition" aria-label="Закрити меню">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                    </button>
                </header>
                <div class="flex-1 overflow-y-auto px-4 py-6">
                    <livewire:client.payments-page :embedded="true" lazy />
                </div>
            </section>
        </template>

        <template x-if="moreStack.includes('promocodes')">
            <section class="absolute inset-0 flex flex-col bg-gray-950 transition-transform duration-300 ease-out" :style="`transform: ${transformFor('promocodes')}`">
                <header class="h-16 px-4 flex items-center justify-between border-b border-gray-800 bg-gray-900/95 backdrop-blur">
                    <button type="button" @click="backMoreScreen()" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-800 text-white hover:bg-gray-700" aria-label="Назад">←</button>
                    <span class="font-semibold text-white text-base">Промокоди</span>
                    <button type="button" @click="closeMoreShell()" class="w-10 h-10 rounded-full flex items-center justify-center bg-gray-800 hover:bg-gray-700 text-white transition" aria-label="Закрити меню">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                    </button>
                </header>
                <div class="flex-1 overflow-y-auto px-4 py-6">
                    <livewire:client.more-placeholder-page page="promocodes" :embedded="true" lazy />
                </div>
            </section>
        </template>

        <template x-if="moreStack.includes('settings')">
            <section class="absolute inset-0 flex flex-col bg-gray-950 transition-transform duration-300 ease-out" :style="`transform: ${transformFor('settings')}`">
                <header class="h-16 px-4 flex items-center justify-between border-b border-gray-800 bg-gray-900/95 backdrop-blur">
                    <button type="button" @click="backMoreScreen()" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-800 text-white hover:bg-gray-700" aria-label="Назад">←</button>
                    <span class="font-semibold text-white text-base">Налаштування</span>
                    <button type="button" @click="closeMoreShell()" class="w-10 h-10 rounded-full flex items-center justify-center bg-gray-800 hover:bg-gray-700 text-white transition" aria-label="Закрити меню">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                    </button>
                </header>
                <div class="flex-1 overflow-y-auto px-4 py-6">
                    <livewire:client.more-placeholder-page page="settings" :embedded="true" lazy />
                </div>
            </section>
        </template>
    </div>
</div>
