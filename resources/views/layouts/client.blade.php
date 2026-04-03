<x-layouts.app title="Poof — Клієнт">

<div
    x-data="{
        moreShellOpen: false,
        moreStack: ['root'],
        deepLinkScreen: @js(request()->string('more_screen')->toString()),
        openMoreRoot() {
            this.moreShellOpen = true;
            this.moreStack = ['root'];
        },
        openMoreScreen(screen) {
            if (! this.moreShellOpen) {
                this.moreShellOpen = true;
                this.moreStack = ['root'];
            }

            if (this.moreStack[this.moreStack.length - 1] === screen) {
                return;
            }

            this.moreStack.push(screen);
        },
        backMoreScreen() {
            if (this.moreStack.length > 1) {
                this.moreStack.pop();
                return;
            }

            this.closeMoreShell();
        },
        closeMoreShell() {
            this.moreShellOpen = false;
            this.moreStack = ['root'];
        },
        isMoreActive(screen) {
            return this.moreStack[this.moreStack.length - 1] === screen;
        },
        screenIndex(screen) {
            return this.moreStack.indexOf(screen);
        },
        transformFor(screen) {
            const index = this.screenIndex(screen);

            if (index === -1) {
                return 'translateX(100%)';
            }

            const activeIndex = this.moreStack.length - 1;

            if (index === activeIndex) {
                return 'translateX(0%)';
            }

            if (index < activeIndex) {
                return 'translateX(-7%)';
            }

            return 'translateX(100%)';
        }
    }"
    x-init="if (window.location.search.includes('open_more=1')) { openMoreRoot(); if (deepLinkScreen) { openMoreScreen(deepLinkScreen); } const params = new URLSearchParams(window.location.search); params.delete('open_more'); params.delete('more_screen'); const cleanQuery = params.toString(); const cleanUrl = window.location.pathname + (cleanQuery ? '?' + cleanQuery : '') + window.location.hash; window.history.replaceState({}, '', cleanUrl); }"
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

    {{-- More shell --}}
    @include('partials.more-sheet')

    {{-- 🔑 Sheets slot (ВАЖНО) --}}
    {{ $sheets ?? '' }}

</div>

</x-layouts.app>
