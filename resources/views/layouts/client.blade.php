<x-layouts.app title="Poof — Клієнт">

<div x-data="{ moreOpen: false }" class="min-h-dvh bg-gray-800 text-white">

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
