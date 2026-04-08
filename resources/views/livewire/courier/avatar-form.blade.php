<form
    class="space-y-5"
    wire:key="courier-avatar-form"
    wire:submit.prevent="save"
    x-data="{ clientError: '' }"
>
    <div class="flex justify-center">
        @if ($avatar)
            <img
                src="{{ $avatar->temporaryUrl() }}"
                class="h-32 w-32 rounded-full bg-gray-800 object-cover"
                alt="Попередній перегляд аватара"
            >
        @else
            <img
                src="{{ optional($courier)->avatar_url }}"
                class="h-32 w-32 rounded-full bg-gray-800 object-cover"
                alt="Поточний аватар"
            >
        @endif
    </div>

    <input
        type="file"
        wire:model="avatar"
        accept="image/*"
        id="courierAvatarInput"
        class="hidden"
        x-on:change="
            const [file] = $event.target.files || [];
            if (!file) {
                clientError = '';
                return;
            }

            const maxBytes = 2 * 1024 * 1024;
            if (file.size > maxBytes) {
                clientError = 'Файл завеликий. Максимум 2 MB.';
                $event.target.value = '';
                $wire.set('avatar', null);
                return;
            }

            clientError = '';
        "
    >

    <label
        for="courierAvatarInput"
        class="block rounded-xl bg-neutral-800 py-3 text-center text-sm font-semibold text-gray-200 transition active:scale-95"
    >
        Обрати фото
    </label>

    <p x-show="clientError" x-text="clientError" class="text-sm text-red-400"></p>

    @error('avatar')
        <p class="text-sm text-red-400">{{ $message }}</p>
    @enderror

    <button
        type="submit"
        wire:loading.attr="disabled"
        wire:target="save,avatar"
        class="w-full rounded-xl bg-poof py-3 font-bold text-[#041015] transition active:scale-95"
    >
        <span wire:loading.remove wire:target="save,avatar">Оновити аватар</span>
        <span wire:loading wire:target="save,avatar">Збереження…</span>
    </button>
</form>
