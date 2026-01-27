<x-poof.ui.card class="mt-6"
    x-data="{
        address: {{ auth()->user()->address
            ? Js::from(auth()->user()->address->full_address)
            : 'null'
        }}
    }"
    x-on:address-saved.window="
        address = 'оновлено'
        setTimeout(() => location.reload(), 300)
    "
>
    <div class="flex justify-between items-center mb-3">
        <h2 class="text-white font-bold text-sm">Домашня адреса</h2>

        <button
            class="text-yellow-400 text-sm font-semibold"
            onclick="window.dispatchEvent(
                new CustomEvent('sheet:open',{detail:{name:'editAddress'}})
            )"
        >
            {{ auth()->user()->address ? 'Редагувати' : 'Додати' }}
        </button>
    </div>

    <p class="text-white font-semibold">
        {{ auth()->user()->address?->full_address ?? 'Адресу ще не додано' }}
    </p>
</x-poof.ui.card>

