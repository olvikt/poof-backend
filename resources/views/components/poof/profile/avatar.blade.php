<button
    type="button"
    class="relative group"
    @click="
        window.dispatchEvent(
            new CustomEvent('sheet:open', {
                detail: { name: 'editAvatar' }
            })
        )
    "
>
    <img
        x-data="{ src: '{{ auth()->user()->avatar_url }}' }"
        x-on:avatar-saved.window="
            if ($event.detail?.avatarUrl) {
                src = $event.detail.avatarUrl + '?' + Date.now()
            }
        "
        :src="src"
        class="w-20 h-20 rounded-full object-cover bg-gray-800"
    />

    <div class="absolute inset-0 bg-black/40 rounded-full
                flex items-center justify-center
                opacity-0 group-hover:opacity-100 transition">
        <span class="text-xs text-white font-semibold">
            Змінити
        </span>
    </div>
</button>