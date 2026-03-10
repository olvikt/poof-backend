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
    x-cloak
>
    <div
        x-show="open"
        class="fixed inset-0 z-[999] flex flex-col"
    >
        {{-- Overlay --}}
        <div
            class="absolute inset-0 bg-black/60 backdrop-blur-sm"
            x-on:click="close()"
        ></div>

        {{-- Sheet --}}
        <div
            x-ref="sheet"
            class="relative bg-neutral-900 rounded-t-2xl shadow-xl flex flex-col h-full translate-y-0 transition-transform duration-300 ease-out"
        >
            {{-- Drag handle --}}
            <div
                class="flex justify-center pt-3 pb-2 cursor-grab"
                x-on:pointerdown="startDrag($event)"
            >
                <div class="w-10 h-1.5 bg-neutral-500 rounded-full"></div>
            </div>

            {{-- Scrollable content --}}
            <div class="flex-1 overflow-y-auto overflow-x-hidden p-4">
                @if($title)
                    <div class="mb-4 border-b border-neutral-800 pb-3">
                        <div class="font-bold text-white">{{ $title }}</div>
                    </div>
                @endif

                {{ $slot }}

                @isset($actions)
                    <div
                        class="
                            mt-4 border-t border-neutral-800 pt-4
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
