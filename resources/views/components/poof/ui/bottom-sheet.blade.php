@props(['name', 'title' => ''])

<div
    x-data="{
        open: false,
        openFor(n) {
            if (n === '{{ $name }}') {
                this.open = true;
                document.body.classList.add('overflow-hidden')
            }
        },
        close() {
            this.open = false;
            document.body.classList.remove('overflow-hidden')
        }
    }"
    x-cloak
    x-show="open"
    x-transition.opacity
    @sheet:open.window="openFor($event.detail?.name)"
    @sheet:close.window="close()"
    @keydown.escape.window="close()"
    class="fixed inset-0 z-[9999]" {{-- 🔥 КЛЮЧЕВО --}}
>
    {{-- Overlay --}}
    <div
        class="absolute inset-0 bg-black/70"
        @click="close()"
    ></div>

    {{-- Sheet --}}
    <div class="absolute inset-x-0 bottom-0">
        <div class="mx-auto max-w-md rounded-t-3xl bg-gray-950 border border-gray-800">
            {{-- Handle --}}
            <div class="pt-3">
                <div class="mx-auto w-12 h-1.5 bg-gray-700 rounded-full"></div>
            </div>

            {{-- Header --}}
            <div class="px-4 py-3 flex justify-between items-center">
                <div class="text-white font-black">
                    {{ $title }}
                </div>

                <button
                    type="button"
                    class="text-white text-xl leading-none px-2 py-1"
                    @click.stop="close()"
                >
                    ✕
                </button>
            </div>

            {{-- Content --}}
            <div class="px-4 pb-6">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
