@props([
    'proofUrls' => [],
    'title' => 'Фото-звіт курʼєра',
])

@php
    $proofItems = collect($proofUrls)->filter(fn ($url) => filled($url))->values();
@endphp

<div
    x-data="{
        open: false,
        index: 0,
        proofs: @js($proofItems),
        openAt(i) {
            if (!this.proofs.length) return;
            this.index = i;
            this.open = true;
        },
        prev() {
            this.index = (this.index - 1 + this.proofs.length) % this.proofs.length;
        },
        next() {
            this.index = (this.index + 1) % this.proofs.length;
        }
    }"
    @keydown.escape.window="open = false"
    @keydown.arrow-left.window="if (open && proofs.length > 1) prev()"
    @keydown.arrow-right.window="if (open && proofs.length > 1) next()"
>
    @if($proofItems->isNotEmpty())
        <div class="mt-2 grid grid-cols-2 gap-2">
            @foreach($proofItems as $proofIndex => $proofUrl)
                <button
                    type="button"
                    @click="openAt({{ $proofIndex }})"
                    class="block overflow-hidden rounded-lg border border-white/10 bg-black/20 text-left"
                >
                    <img
                        src="{{ $proofUrl }}"
                        alt="{{ $title }}, фото {{ $proofIndex + 1 }}"
                        class="h-24 w-full object-cover"
                    />
                </button>
            @endforeach
        </div>
    @else
        <div class="mt-2 text-xs text-sky-100/80">Фотозвіт відсутній</div>
    @endif

    <div
        x-show="open && proofs.length"
        x-cloak
        class="fixed inset-0 z-[100] bg-black/85"
        @click.self="open = false"
        role="dialog"
        aria-modal="true"
        aria-label="{{ $title }}"
    >
        <div class="flex h-full w-full flex-col p-4 sm:p-6">
            <div class="mb-3 flex items-center justify-between text-white">
                <div>
                    <p class="text-sm font-semibold">{{ $title }}</p>
                    <p class="text-xs text-gray-300" x-text="`Фото ${index + 1} з ${proofs.length}`"></p>
                </div>
                <button
                    type="button"
                    class="rounded border border-white/30 px-3 py-1 text-sm"
                    aria-label="Закрити"
                    @click="open = false"
                >
                    Закрити ×
                </button>
            </div>

            <div class="relative flex flex-1 items-center justify-center">
                <img
                    :src="proofs[index]"
                    :alt="`{{ $title }}, фото ${index + 1}`"
                    class="max-h-[85vh] w-auto max-w-full rounded-lg object-contain"
                />

                <div x-show="proofs.length > 1">
                    <button
                        type="button"
                        class="absolute left-1 top-1/2 -translate-y-1/2 rounded-full bg-black/60 px-3 py-2 text-white"
                        @click="prev()"
                    >
                        ‹
                    </button>
                    <button
                        type="button"
                        class="absolute right-1 top-1/2 -translate-y-1/2 rounded-full bg-black/60 px-3 py-2 text-white"
                        @click="next()"
                    >
                        ›
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
