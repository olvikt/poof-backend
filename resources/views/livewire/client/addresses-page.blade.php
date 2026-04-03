<div class="rounded-xl bg-gray-950 px-4 pb-28 pt-4 text-white shadow-[0_0_0_1px_rgba(74,222,128,0.25)]" @if($embedded) data-more-shell-screen="addresses" @endif>
    @unless($embedded)
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold">Мої адреси</h1>
                <p class="text-sm text-gray-400">Один список адрес для профілю, замовлень і підписок.</p>
            </div>
            <a href="{{ route('client.home', ['open_more' => 1, 'more_screen' => 'addresses']) }}" class="rounded-xl border border-gray-700 px-3 py-2 text-sm text-gray-200">Закрити</a>
        </div>
    @endunless

    <livewire:client.address-manager />

    @include('livewire.client.partials.address-form-sheet', ['wireKey' => $embedded ? 'more-addresses' : 'addresses-page'])
</div>
