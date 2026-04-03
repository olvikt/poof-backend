<x-layouts.app title="Poof — Клієнт">

<div
    x-data="{ moreOpen: @js(request()->boolean('open_more')) }"
    x-init="if (window.location.search.includes('open_more=')) { const cleanUrl = window.location.pathname + window.location.hash; window.history.replaceState({}, '', cleanUrl); }"
    class="min-h-dvh bg-gray-800 text-white"
>

    {{-- Header --}}
    @include('partials.header')

    {{-- Page content --}}
    <main class="max-w-md mx-auto py-4 pb-32">
        {{ $slot }}
    </main>

    {{-- Bottom navigation --}}
    <div class="max-w-md mx-auto">
        @include('partials.bottom-nav')
    </div>

    {{-- More sheet --}}
    @include('partials.more-sheet')

    {{-- 🔑 Sheets slot (ВАЖНО) --}}
    {{ $sheets ?? '' }}

</div>

</x-layouts.app>
