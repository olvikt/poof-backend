@props([
    'name',
    'title' => null,
    'hideHeader' => false,
    'bodyClass' => 'px-4 pb-6',
    'panelClass' => 'rounded-t-2xl bg-neutral-900',
    'actionsClass' => 'border-t border-neutral-800 bg-neutral-900 p-4',
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
                class="absolute inset-x-0 bottom-0 flex h-[100dvh] flex-col {{ $panelClass }}"
            >
                @unless($hideHeader)
                    <div class="flex items-center justify-between border-b border-neutral-800 px-3 py-3">
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
                @endunless

                <div class="flex-1 overflow-y-auto overflow-x-hidden {{ $bodyClass }}">
                    {{ $slot }}
                </div>

                @if(isset($actions))
                    <div class="{{ $actionsClass }}">
                        {{ $actions }}
                    </div>
                @endif
            </div>
        </div>
    </template>
</div>
