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
                class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                @click="close()"
            ></div>

            <div
                x-ref="sheet"
                class="absolute inset-x-0 bottom-0 h-[100dvh] bg-neutral-900 rounded-t-2xl flex flex-col transition-transform duration-300"
            >
                <div
                    class="flex justify-center pt-3 pb-2 cursor-grab"
                    @pointerdown="startDrag($event)"
                >
                    <div class="w-10 h-1.5 bg-neutral-500 rounded-full"></div>
                </div>

                <div class="px-4 pb-2 text-center text-sm font-semibold text-white/80 shrink-0">
                    {{ $title }}
                </div>

                <div class="flex-1 overflow-y-auto overflow-x-hidden px-4 pb-6">
                    {{ $slot }}
                </div>

                <div class="p-4 border-t border-neutral-800 bg-neutral-900">
                    {{ $actions ?? '' }}
                </div>
            </div>
        </div>
    </template>
</div>
