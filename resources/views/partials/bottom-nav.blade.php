<nav class="fixed bottom-0 inset-x-0 z-30" aria-label="Основна навігація">
    <div class="mx-auto max-w-md h-20 pb-safe
                bg-gradient-to-t from-gray-950 via-gray-900 to-gray-800
                bottom-nav">

        <div class="grid grid-cols-5 h-full">

            {{-- Home --}}
            <a href="{{ route('client.home') }}"
               aria-current="{{ request()->routeIs('client.home') ? 'page' : 'false' }}"
               class="group relative flex flex-col items-center justify-center gap-1
                      {{ request()->routeIs('client.home')
                            ? 'text-yellow-400'
                            : 'text-gray-400 hover:text-gray-200' }}
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400/40">

                {{-- icon --}}
                <svg width="24" height="24" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 10.5L12 3l9 7.5"/>
                    <path d="M5 10v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V10"/>
                </svg>

                <span class="text-[11px] font-medium">Головна</span>

                {{-- underline --}}
                <span class="pointer-events-none absolute bottom-2 left-2 right-2 h-0.5
                             bg-gradient-to-r from-yellow-400 via-yellow-300 to-yellow-400
                             rounded-full
                             transform scale-x-0 transition-transform duration-300
                             {{ request()->routeIs('client.home') ? 'scale-x-100' : 'group-hover:scale-x-100' }}">
                </span>
            </a>

            {{-- Orders --}}
            <a href="{{ route('client.orders') }}"
               aria-current="{{ request()->routeIs('client.orders') ? 'page' : 'false' }}"
               class="group relative flex flex-col items-center justify-center gap-1
                      {{ request()->routeIs('client.orders')
                            ? 'text-yellow-400'
                            : 'text-gray-400 hover:text-gray-200' }}
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400/40">

                <svg width="24" height="24" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                </svg>

                <span class="text-[11px] font-medium">Замовлення</span>
                <span class="pointer-events-none absolute bottom-2 left-2 right-2 h-0.5
                             bg-gradient-to-r from-yellow-400 via-yellow-300 to-yellow-400
                             rounded-full
                             transform scale-x-0 transition-transform duration-300
                             {{ request()->routeIs('client.orders') ? 'scale-x-100' : 'group-hover:scale-x-100' }}">
                </span>
            </a>

            {{-- Create --}}
            <a href="{{ route('client.order.create') }}"
               class="flex flex-col items-center justify-center">
                <div class="w-12 h-12 rounded-2xl bg-yellow-400 text-black
                            flex items-center justify-center
                            shadow-lg shadow-yellow-400/25
                            hover:shadow-yellow-400/40
                            focus-visible:ring-2 focus-visible:ring-yellow-400/50
                            active:scale-95 transition">
                    <svg width="24" height="24" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14"/>
                        <path d="M5 12h14"/>
                    </svg>
                </div>
            </a>

            {{-- Profile --}}
            <a href="{{ route('client.profile') }}"
               aria-current="{{ request()->routeIs('client.profile') ? 'page' : 'false' }}"
               class="group relative flex flex-col items-center justify-center gap-1
                      {{ request()->routeIs('client.profile')
                            ? 'text-yellow-400'
                            : 'text-gray-400 hover:text-gray-200' }}">

                <svg width="24" height="24" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>

                <span class="text-[11px] font-medium">Профіль</span>
                <span class="pointer-events-none absolute bottom-2 left-2 right-2 h-0.5
                             bg-gradient-to-r from-yellow-400 via-yellow-300 to-yellow-400
                             rounded-full
                             transform scale-x-0 transition-transform duration-300
                             {{ request()->routeIs('client.profile') ? 'scale-x-100' : 'group-hover:scale-x-100' }}">
                </span>
            </a>

            {{-- More --}}
            <button
                @click="moreOpen = true"
                class="group relative flex flex-col items-center justify-center gap-1
                       text-gray-400 hover:text-gray-200
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400/40">

                <svg width="24" height="24" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="4" y1="7"  x2="20" y2="7"/>
                    <line x1="4" y1="12" x2="20" y2="12"/>
                    <line x1="4" y1="17" x2="20" y2="17"/>
                </svg>

                <span class="text-[11px] font-medium">Більше</span>
            </button>

        </div>
    </div>
</nav>
