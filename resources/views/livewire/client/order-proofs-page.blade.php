<div class="space-y-4 px-4">
    <div class="rounded-xl border border-gray-700 bg-gray-900 p-4">
        <h1 class="text-lg font-semibold text-white">Фото-звіт курʼєра</h1>
        <p class="mt-1 text-sm text-gray-300">Замовлення #{{ $order->id }}</p>
    </div>

    @if($proofUrls->isEmpty())
        <div class="rounded-xl border border-gray-700 bg-gray-900 p-4 text-sm text-gray-300">
            Фотозвіт відсутній
        </div>
    @else
        <div class="space-y-3">
            @foreach($proofUrls as $index => $proofUrl)
                <article class="rounded-xl border border-gray-700 bg-gray-900 p-3">
                    <div class="mb-2 text-xs font-semibold text-sky-200">Фото {{ $index + 1 }} з {{ $proofUrls->count() }}</div>
                    <img src="{{ $proofUrl }}" alt="Фото-звіт курʼєра {{ $index + 1 }}" class="w-full rounded-lg border border-gray-700 object-cover" loading="lazy">
                </article>
            @endforeach
        </div>
    @endif

    <a
        href="{{ route('client.orders', ['highlight' => $order->id]) }}"
        class="inline-flex items-center rounded-lg border border-gray-600 px-4 py-2 text-sm font-medium text-gray-200 hover:bg-gray-800"
    >
        Повернутися до замовлення
    </a>
</div>
