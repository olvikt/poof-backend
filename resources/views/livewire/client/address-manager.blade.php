<div>

    {{-- 📦 Card --}}
    <x-poof.ui.card class="mt-6 pb-28">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-white font-bold text-sm">
                Мої адреси
            </h2>

            <button
                wire:click="create"
                type="button"
                class="text-yellow-400 text-sm font-semibold hover:opacity-80 transition"
            >
                + Додати
            </button>
        </div>

        {{-- Addresses --}}
@forelse ($addresses as $address)
    <div
        wire:key="address-{{ $address->id }}"
        class="p-4 mb-3 rounded-xl border transition
            {{ $address->is_default
                ? 'border-yellow-400 bg-yellow-400/10'
                : 'border-neutral-700 bg-neutral-800 hover:border-neutral-600' }}"
    >
        {{-- Card content --}}
        <div class="flex items-start gap-3">

            {{-- Info (tap = edit) --}}
            <button
                type="button"
                wire:click="edit({{ $address->id }})"
                class="min-w-0 flex-1 text-left"
            >
                <div class="flex items-center gap-2 min-w-0">
                    <span class="font-semibold text-white truncate">
                        {{ $address->label_title ?? 'Адреса' }}
                    </span>

                    @if ($address->is_default)
                        <span class="text-xs text-yellow-400 shrink-0">
                            • основна
                        </span>
                    @endif

                    @if (! $address->is_verified)
                        <span class="text-xs text-yellow-400 shrink-0">
                            • потребує уточнення
                        </span>
                    @endif
                </div>

                <p class="text-sm text-gray-300 mt-1 line-clamp-2">
                    {{ $address->address_text ?? $address->full_address }}
                </p>
            </button>

            {{-- Menu (⋮) --}}
            <button
    type="button"
    wire:click.stop="openActions({{ $address->id }})"
    class="shrink-0 w-9 h-9 rounded-xl
           bg-neutral-900/40 hover:bg-neutral-900/60
           border border-neutral-700
           flex items-center justify-center
           text-gray-300 hover:text-white
           transition"
    aria-label="Дії з адресою"
    title="Дії"
>
    <span class="text-lg leading-none">⋮</span>
</button>

        </div>
    </div>
@empty
    <p class="text-sm text-gray-400">
        Адреси ще не додані
    </p>
@endforelse

    </x-poof.ui.card>

    {{-- 🗑 Delete confirm sheet --}}
	<x-poof.ui.bottom-sheet name="deleteAddress" title="Видалити адресу?">
		<p class="text-sm text-gray-300 leading-relaxed">
			Цю адресу буде
			<span class="text-red-400 font-semibold">видалено назавжди</span>.
			Ви не зможете її відновити.
		</p>
		<x-slot:actions>
			<div class="flex gap-3">
				<button
					wire:click="deleteConfirmed"
					type="button"
					class="flex-1 bg-red-500 hover:bg-red-400
						   text-black font-bold py-3 rounded-2xl
						   active:scale-95 transition"
				>
					Видалити
				</button>

				<button
					wire:click="cancelDelete"
					type="button"
					class="flex-1 bg-neutral-800 hover:bg-neutral-700
						   text-gray-200 py-3 rounded-2xl
						   active:scale-95 transition"
				>
					Скасувати
				</button>
			</div>
		</x-slot:actions>
	</x-poof.ui.bottom-sheet>



{{-- ⋮ Actions sheet --}}
<x-poof.ui.bottom-sheet name="addressActions">
    {{-- Header --}}
    <div class="mb-4">
        <h3 class="text-white font-bold text-base">
            Дії з адресою
        </h3>

        @if ($actionsAddress)
            <p class="text-sm text-gray-400 mt-1 line-clamp-2">
                {{ $actionsAddress->address_text ?? $actionsAddress->full_address }}
            </p>
        @endif
    </div>

    {{-- Actions --}}
    <div class="space-y-3">

        {{-- Set default (only if not default) --}}
        @if ($actionsAddress && ! $actionsAddress->is_default)
            <button
                type="button"
                wire:click="actionSetDefault"
                class="w-full bg-neutral-800 hover:bg-neutral-700
                       text-gray-100 font-semibold py-3 rounded-2xl
                       active:scale-95 transition text-left px-4"
            >
                ⭐ Зробити основною
            </button>
        @endif
		
		{{-- Order from this address --}}
		<button
			type="button"
			wire:click="orderFromAddress"
			class="w-full bg-yellow-400 hover:bg-yellow-300
				   text-black font-bold py-3 rounded-2xl
				   active:scale-95 transition text-left px-4"
		>
			🚚 Замовити з цього адреса
		</button>

        <button
            type="button"
            wire:click="actionEdit"
            class="w-full bg-neutral-800 hover:bg-neutral-700
                   text-gray-100 font-semibold py-3 rounded-2xl
                   active:scale-95 transition text-left px-4"
        >
            ✏️ Редагувати
        </button>

        <button
            type="button"
            wire:click="actionDelete"
            class="w-full bg-neutral-800 hover:bg-neutral-700
                   text-red-300 font-semibold py-3 rounded-2xl
                   active:scale-95 transition text-left px-4"
        >
            🗑 Видалити
        </button>

        <button
            type="button"
            wire:click="closeActions"
            class="w-full bg-neutral-900 hover:bg-neutral-800
                   text-gray-200 py-3 rounded-2xl
                   active:scale-95 transition"
        >
            Скасувати
        </button>
    </div>
</x-poof.ui.bottom-sheet>

</div>
