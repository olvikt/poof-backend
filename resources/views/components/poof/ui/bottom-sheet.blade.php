@props([
    'name',
    'title' => null,
])

<div
    wire:ignore.self
    x-data="{
        open: false,
        name: @js($name),

        openSheet(e) {
            if (!e?.detail || e.detail.name !== this.name) return
            this.open = true

            document.documentElement.classList.add(
                'overflow-hidden',
                'sheet-open'
            )
        },

        closeSheet(e) {
            if (e?.detail?.name && e.detail.name !== this.name) return
            this.open = false

            document.documentElement.classList.remove(
                'overflow-hidden',
                'sheet-open'
            )
        }
    }"
    x-on:sheet:open.window="openSheet($event)"
    x-on:sheet:close.window="closeSheet($event)"
    x-on:keydown.escape.window="closeSheet()"
    x-cloak
>

    <div
        x-show="open"
        x-transition
        class="fixed inset-0 z-50"
    >
        {{-- Overlay --}}
        <div class="absolute inset-0 bg-black/60 pointer-events-none"></div>

        {{-- Bottom sheet container --}}
        <div class="absolute inset-x-0 bottom-0 top-0 flex flex-col pointer-events-auto">
            <div class="bg-neutral-900 rounded-t-2xl shadow-xl flex flex-col h-full">

                {{-- Header --}}
                <div class="p-4 border-b border-neutral-800 shrink-0">
                    <div class="flex items-center justify-between">
                        <div class="font-bold text-white">
                            {{ $title }}
                        </div>

                        <button
                            type="button"
                            class="text-gray-400 hover:text-white transition"
                            x-on:click="closeSheet()"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                {{-- Body (scrollable content) --}}
                <div class="modal-body flex-1 overflow-y-auto overflow-x-hidden p-4">
                    {{ $slot }}
                </div>

                {{-- Actions (fixed, always visible) --}}
                @isset($actions)
                    <div
                        class="
                            p-4 border-t border-neutral-800 shrink-0
                            pb-[calc(4.5rem+env(safe-area-inset-bottom))]
                        "
                    >
                        {{ $actions }}
                    </div>
                @endisset

            </div>
        </div>
    </div>

</div>
