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
                <div class="flex justify-between items-start gap-3">

                    {{-- Info --}}
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-white truncate">
                                {{ $address->label_title ?? 'Адреса' }}
                            </span>

                            @if ($address->is_default)
                                <span class="text-xs text-yellow-400">
                                    • основна
                                </span>
                            @endif
							@if (! $address->is_verified)
								<span class="text-xs text-yellow-400 ml-2">
									• потребує уточнення
								</span>
							@endif
							
                        </div>

                       <p class="text-sm text-gray-300 mt-1 truncate">
							{{ $address->address_text ?? $address->full_address }}
					 </p>
                    </div>

                    {{-- Actions --}}
                    <div class="flex flex-col items-end gap-2 text-xs shrink-0">

                        @unless ($address->is_default)
                            <button
                                wire:click="setDefault({{ $address->id }})"
                                type="button"
                                class="text-gray-400 hover:text-yellow-400 transition"
                            >
                                Зробити основною
                            </button>
                        @endunless

                        <div class="flex items-center gap-3">
                            <button
                                wire:click="edit({{ $address->id }})"
                                type="button"
                                class="text-yellow-400 hover:opacity-80 transition"
                            >
                                Редагувати
                            </button>

                            <button
                                wire:click="confirmDelete({{ $address->id }})"
                                type="button"
                                title="Видалити адресу"
                                class="text-red-400 hover:text-red-300 transition"
                            >
                                🗑
                            </button>
                        </div>

                    </div>
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



</div>
