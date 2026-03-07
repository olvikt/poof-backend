<form
    class="space-y-5"
    wire:key="avatar-form"
    wire:submit.prevent="save"
>
    <div class="flex justify-center">
        @if ($avatar)
            <img
                src="{{ $avatar->temporaryUrl() }}"
                class="w-32 h-32 rounded-full object-cover bg-gray-800"
            >
        @else
            <img
                src="{{ auth()->user()->avatar_url }}"
                class="w-32 h-32 rounded-full object-cover bg-gray-800"
            >
        @endif
    </div>

    <input
        type="file"
        wire:model="avatar"
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

    @error('avatar')
        <p class="text-sm text-red-400">{{ $message }}</p>
    @enderror

    <button
        type="submit"
        wire:loading.attr="disabled"
        wire:target="save,avatar"
        class="w-full bg-yellow-400 text-black font-bold py-3 rounded-xl
               active:scale-95 transition"
    >
        <span wire:loading.remove wire:target="save,avatar">
            Зберегти
        </span>

        <span wire:loading wire:target="save,avatar">
            Збереження…
        </span>
    </button>
</form>
