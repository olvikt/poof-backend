@props(['user'])

<x-poof.ui.card>
    <div class="flex justify-between items-center mb-3">
        <h2 class="text-white font-bold text-sm">Особисті дані</h2>

        <button
            class="text-yellow-400 text-sm font-semibold"
            data-e2e="client-profile-edit-open"
            onclick="window.dispatchEvent(new CustomEvent('sheet:open',{detail:{name:'editProfile'}})); if (window.Livewire) { window.Livewire.dispatch('profile:open'); }"
        >
            Редагувати
        </button>
    </div>

    <div class="space-y-3">
        <div>
            <p class="text-xs text-gray-500">ПІБ</p>
            <p class="text-white font-semibold">{{ $user?->name ?? '—' }}</p>
        </div>

        <div>
            <p class="text-xs text-gray-500">Телефон</p>
            <p class="text-white font-semibold">{{ $user?->phone ?? '—' }}</p>
        </div>

        <div>
            <p class="text-xs text-gray-500">Email</p>
            <p class="text-white font-semibold">{{ $user?->email ?? '—' }}</p>
        </div>
    </div>
</x-poof.ui.card>
