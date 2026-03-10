@props([
    'name',
    'title' => null,
])

<div
    wire:ignore.self
    x-data="bottomSheet(@js($name))"
    x-on:sheet:open.window="openSheet($event)"
    x-on:sheet:close.window="closeSheet($event)"
    x-on:keydown.escape.window="close()"
>
    <template x-teleport="body">
        <div
            x-show="open"
            x-transition
            x-cloak
            class="fixed inset-0 z-[9999] flex items-end"
        >
            <div
                class="absolute inset-0 bg-black/60"
                @click="close()"
            ></div>

            <div
                x-ref="sheet"
                class="absolute inset-x-0 bottom-0 h-[100dvh] bg-neutral-900 rounded-t-2xl flex flex-col"
            >
                <div class="flex items-center justify-between px-3 py-3 border-b border-neutral-800">
                    <h2 class="text-sm font-semibold text-white">
                        {{ $title }}
                    </h2>

                    <button
                        type="button"
                        @click="close()"
                        class="text-neutral-400 hover:text-white"
                    >
                        ✕
                    </button>
                </div>


                <div class="flex-1 overflow-y-auto overflow-x-hidden px-4 pb-6">
                    {{ $slot }}
                </div>

                <div class="border-t border-neutral-800 bg-neutral-900">
                    {{ $actions ?? '' }}
                </div>
            </div>
        </div>
    </template>
</div>
