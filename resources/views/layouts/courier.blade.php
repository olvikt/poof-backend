<x-layouts.app title="POOF — Курʼєр">

<div x-data="{ logoutOpen: false, settingsOpen: false }"
    class="min-h-dvh bg-gray-800 flex justify-center text-white"
>

    {{-- MOBILE CONTAINER --}}
    <div class="relative w-full max-w-md min-h-dvh flex flex-col bg-gray-800">

        {{-- ================= HEADER ================= --}}
        <header class="sticky top-0 z-40 bg-gray-900 border-b border-gray-700">
            <div class="px-4 py-3 flex items-center justify-between">

                <div class="font-black tracking-wide">
                    🚴 <span class="text-poof">POOF</span>
                    <span class="text-gray-400 text-xs ml-1">Курʼєр</span>
                </div>

                <livewire:courier.online-toggle />

            </div>
        </header>


        {{-- ================= CONTENT ================= --}}
        <main class="flex-1 overflow-y-auto px-4 py-4 pb-28">
            {{ $slot }}
        </main>


        {{-- ================= BOTTOM NAV ================= --}}
        <nav
            class="fixed bottom-0 left-1/2 -translate-x-1/2
                   w-full max-w-md
                   bg-gray-900 border-t border-gray-700
                   z-50"
        >
            <div class="flex justify-around py-2 text-xs">

                {{-- Available --}}
                <a
                    href="{{ route('courier.orders') }}"
                    wire:navigate
                    class="flex flex-col items-center gap-1 transition
                        {{ request()->routeIs('courier.orders')
                            ? 'text-poof'
                            : 'text-gray-400 hover:text-white'
                        }}"
                >
                    <span class="text-lg">📦</span>
                    Доступні
                </a>

                {{-- My Orders --}}
                <a
                    href="{{ route('courier.my-orders') }}"
                    wire:navigate
                    class="flex flex-col items-center gap-1 transition
                        {{ request()->routeIs('courier.my-orders')
                            ? 'text-poof'
                            : 'text-gray-400 hover:text-white'
                        }}"
                >
                    <span class="text-lg">🚴‍♂️</span>
                    Мої
                </a>

                {{-- Settings (без route чтобы не падало) --}}
				<button
					type="button"
					class="flex flex-col items-center gap-1 text-gray-400 hover:text-white transition"
					@click="settingsOpen = true"
				>
				<span class="text-lg">⚙️</span>
                    Налаштування
                </button>

                {{-- Logout --}}
                <button
                    type="button"
                    class="flex flex-col items-center gap-1 text-gray-400 hover:text-red-400 transition"
                    @click="logoutOpen = true"
                >
                    <span class="text-lg">⏻</span>
                    Вийти
                </button>

            </div>
        </nav>


        {{-- ================= LOGOUT MODAL ================= --}}
        <div
            x-show="logoutOpen"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-[999] bg-black/70 flex items-center justify-center"
        >
            <div
                @click.away="logoutOpen = false"
                class="bg-zinc-900 rounded-3xl p-6 w-80 shadow-2xl text-center"
            >

                <div class="text-lg font-semibold mb-4">
                    Вийти з акаунту?
                </div>

                <div class="flex gap-3">

                    <button
                        @click="logoutOpen = false"
                        class="flex-1 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 transition"
                    >
                        Скасувати
                    </button>

<form method="POST" action="{{ route('logout') }}" class="flex-1">
    @csrf
    <button
        type="submit"
        class="w-full py-3 rounded-xl bg-red-500 text-black font-bold text-center hover:bg-red-600 transition"
    >
        Вийти
    </button>
</form>

                </div>

            </div>
        </div>


		{{-- SETTINGS MODAL --}}
		<div
			x-show="settingsOpen"
			x-cloak
			x-transition.opacity
			class="fixed inset-0 z-[998] bg-black/70 flex items-center justify-center"
		>
			<div
				@click.away="settingsOpen = false"
				class="bg-zinc-900 rounded-3xl p-6 w-80 shadow-2xl"
			>

				<div class="text-lg font-semibold mb-4 text-center">
					Налаштування
				</div>

				<div class="space-y-3 text-sm">

					<a href="#" class="block py-3 px-4 rounded-xl bg-zinc-800 hover:bg-zinc-700 transition">
						💳 Кошелёк
					</a>

					<a href="#" class="block py-3 px-4 rounded-xl bg-zinc-800 hover:bg-zinc-700 transition">
						👤 Профіль
					</a>

					<a href="#" class="block py-3 px-4 rounded-xl bg-zinc-800 hover:bg-zinc-700 transition">
						🎁 Акції
					</a>

					<button
						@click="settingsOpen = false"
						class="w-full py-3 mt-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 transition"
					>
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


