<div
    class="space-y-5"
    wire:key="avatar-form"
>

    {{-- PREVIEW --}}
	<div class="flex justify-center">
		@if ($photo)
			<img
				src="{{ $photo->temporaryUrl() }}"
				class="w-32 h-32 rounded-full object-cover bg-gray-800"
			>
		@else
			<img
				src="{{ auth()->user()->avatar_url }}"
				class="w-32 h-32 rounded-full object-cover bg-gray-800"
			>
		@endif
	</div>

    {{-- FILE INPUT (ТОЛЬКО Livewire) --}}
    <input
        type="file"
        wire:model="photo"
        accept="image/*"
        id="avatarInput"
        class="hidden"
    >

    <label
        for="avatarInput"
        class="block text-center bg-neutral-800 rounded-xl py-3
               text-sm font-semibold text-gray-200
               active:scale-95 transition"
    >
        Обрати фото
    </label>

    {{-- SAVE --}}
<button
    type="button"
    wire:click="save"
    wire:loading.attr="disabled"
    wire:target="save"
    class="w-full bg-yellow-400 text-black font-bold py-3 rounded-xl
           active:scale-95 transition"
>
    <span wire:loading.remove wire:target="save">
        Зберегти
    </span>

    <span wire:loading wire:target="save">
        Збереження…
    </span>
</button>

</div>
