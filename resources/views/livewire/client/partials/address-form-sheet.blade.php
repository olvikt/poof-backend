<template x-teleport="body">
    <x-poof.ui.bottom-sheet
        name="addressForm"
        title="Адреса"
        :hide-header="true"
        body-class="overflow-visible px-0 pb-0"
        panel-class="overflow-hidden bg-transparent"
        actions-class="border-t border-neutral-800 bg-neutral-900/95 p-4 backdrop-blur"
    >
        <livewire:client.address-form wire:key="address-form-{{ $wireKey ?? 'default' }}" />

        <x-slot:actions>
            <button
                type="submit"
                form="address-form"
                class="w-full bg-yellow-400 text-black font-semibold py-3 rounded-xl active:scale-95 transition"
            >
                Зберегти адресу
            </button>
        </x-slot:actions>
    </x-poof.ui.bottom-sheet>
</template>
