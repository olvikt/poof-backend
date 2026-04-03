{{-- Fullscreen "More" menu --}}
<div
    x-show="moreOpen"
    @keydown.escape.window="moreOpen = false"
    x-transition.opacity
    class="fixed inset-0 z-[100]"
    style="display: none;"
>

    {{-- backdrop --}}
    <div
        class="absolute inset-0 bg-black/70"
        @click="moreOpen = false"
    ></div>

    {{-- panel --}}
    <div
        x-transition:enter="transition transform duration-300"
        x-transition:enter-start="translate-y-4 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition transform duration-200"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-4 opacity-0"

        class="absolute inset-0
               bg-gray-950
               flex flex-col"
    >

{{-- Header --}}
<div class="h-16 px-4 flex items-center justify-between
            border-b border-gray-800 bg-gray-900/95 backdrop-blur">

    {{-- Left: back hint --}}
    <span class="text-sm text-gray-400">
        Меню
    </span>

    {{-- Title --}}
    <span class="font-semibold text-white text-base">
        Більше
    </span>

    {{-- Close --}}
    <button
        @click="moreOpen = false"
        class="w-10 h-10 rounded-full
               flex items-center justify-center
               bg-gray-800 hover:bg-gray-700
               text-white transition"
        aria-label="Закрити меню"
    >
        <svg width="22" height="22" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6L6 18"/>
            <path d="M6 6l12 12"/>
        </svg>
    </button>
</div>

        {{-- Menu --}}
        <div class="flex-1 overflow-y-auto px-4 py-6">
<nav class="text-sm text-gray-200">

    {{-- Item --}}
    <a href="{{ route('client.subscriptions') }}" @click="moreOpen = false"
       class="flex items-center gap-4 py-4
              border-b border-gray-800/60
              hover:bg-gray-800/60 transition
              group">
        <span class="w-6 text-center">⭐</span>
        <span class="flex-1">Підписка</span>

        {{-- Chevron --}}
        <svg class="w-4 h-4 text-gray-500 group-hover:text-gray-400 transition"
             viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 18l6-6-6-6"/>
        </svg>
    </a>

    <a href="{{ route('client.addresses') }}" @click="moreOpen = false"
       class="flex items-center gap-4 py-4
              border-b border-gray-800/60
              hover:bg-gray-800/60 transition
              group">
        <span class="w-6 text-center">📍</span>
        <span class="flex-1">Мої адреси</span>
        <svg class="w-4 h-4 text-gray-500 group-hover:text-gray-400 transition"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 18l6-6-6-6"/>
        </svg>
    </a>

    <a href="{{ route('client.billing') }}" @click="moreOpen = false"
       class="flex items-center gap-4 py-4
              border-b border-gray-800/60
              hover:bg-gray-800/60 transition
              group">
        <span class="w-6 text-center">💳</span>
        <span class="flex-1">Оплата</span>
        <svg class="w-4 h-4 text-gray-500 group-hover:text-gray-400 transition"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 18l6-6-6-6"/>
        </svg>
    </a>

    <a href="{{ route('client.more.placeholder', ['page' => 'promocodes']) }}" @click="moreOpen = false"
       class="flex items-center gap-4 py-4
              border-b border-gray-800/60
              hover:bg-gray-800/60 transition
              group">
        <span class="w-6 text-center">🎁</span>
        <span class="flex-1">Промокоди</span>
        <svg class="w-4 h-4 text-gray-500 group-hover:text-gray-400 transition"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 18l6-6-6-6"/>
        </svg>
    </a>

    <a href="{{ route('client.support') }}" @click="moreOpen = false"
       class="flex items-center gap-4 py-4
              border-b border-gray-800/60
              hover:bg-gray-800/60 transition
              group">
        <span class="w-6 text-center">📞</span>
        <span class="flex-1">Підтримка</span>
        <svg class="w-4 h-4 text-gray-500 group-hover:text-gray-400 transition"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 18l6-6-6-6"/>
        </svg>
    </a>

    {{-- Last item (NO divider) --}}
    <a href="{{ route('client.more.placeholder', ['page' => 'settings']) }}" @click="moreOpen = false"
       class="flex items-center gap-4 py-4
              hover:bg-gray-800/60 transition
              group">
        <span class="w-6 text-center">⚙️</span>
        <span class="flex-1">Налаштування</span>
        <svg class="w-4 h-4 text-gray-500 group-hover:text-gray-400 transition"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 18l6-6-6-6"/>
        </svg>
    </a>

</nav>

        </div>

    </div>
</div>

