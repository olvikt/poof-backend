<div
    x-show="moreShellOpen"
    @keydown.escape.window="closeMoreShell()"
    x-transition.opacity
    class="fixed inset-0 z-[100]"
    style="display: none;"
>
    <div class="absolute inset-0 bg-black/70" @click="closeMoreShell()"></div>

    <div class="absolute inset-0 overflow-hidden bg-gray-950">
        @php
            $screens = ['root', 'subscriptions', 'addresses', 'billing', 'promocodes', 'settings'];
            $titles = [
                'root' => 'Більше',
                'subscriptions' => 'Підписка',
                'addresses' => 'Мої адреси',
                'billing' => 'Оплата',
                'promocodes' => 'Промокоди',
                'settings' => 'Налаштування',
            ];
        @endphp

        @foreach($screens as $screen)
            <section
                class="absolute inset-0 flex flex-col bg-gray-950 transition-transform duration-300 ease-out"
                :style="`transform: ${transformFor('{{ $screen }}')}`"
                x-show="moreStack.includes('{{ $screen }}')"
                style="display: none;"
            >
                <header class="h-16 px-4 flex items-center justify-between border-b border-gray-800 bg-gray-900/95 backdrop-blur">
                    @if ($screen === 'root')
                        <span class="text-sm text-gray-400">Меню</span>
                    @else
                        <button
                            type="button"
                            @click="backMoreScreen()"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-800 text-white hover:bg-gray-700"
                            aria-label="Назад"
                        >
                            ←
                        </button>
                    @endif

                    <span class="font-semibold text-white text-base">{{ $titles[$screen] }}</span>

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
                    @if ($screen === 'root')
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
                    @elseif ($screen === 'subscriptions')
                        <livewire:client.subscriptions-page :embedded="true" />
                    @elseif ($screen === 'addresses')
                        <livewire:client.addresses-page :embedded="true" />
                    @elseif ($screen === 'billing')
                        <livewire:client.payments-page :embedded="true" />
                    @elseif ($screen === 'promocodes')
                        <livewire:client.more-placeholder-page page="promocodes" :embedded="true" />
                    @elseif ($screen === 'settings')
                        <livewire:client.more-placeholder-page page="settings" :embedded="true" />
                    @endif
                </div>
            </section>
        @endforeach
    </div>
</div>
