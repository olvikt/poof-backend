<div class="px-4 pt-5 pb-28">

    {{-- Header --}}
    <div class="flex items-start justify-between gap-3 mb-5">
        <div>
            <p class="text-gray-400 text-sm">Вітаємо,</p>
            <h1 class="text-white text-2xl font-black leading-tight">
                {{ auth()->user()->name ?? 'друже' }} 👋
            </h1>
            <p class="text-gray-400 text-sm mt-1">
                Poof — і вже чисто!
            </p>
        </div>

        {{-- маленький бейдж/статус --}}
        <!--<div class="px-3 py-2 rounded-xl border border-gray-800 bg-gray-900/60">
            <p class="text-xs text-gray-400">Статус</p>
            <p class="text-sm font-semibold text-yellow-400">Online</p>
        </div>-->
    </div>

    {{-- Main CTA --}}
    <a href="{{ route('client.order.create') }}"
       class="block w-full rounded-2xl py-4 text-center font-black
              bg-yellow-400 text-black
              shadow-lg shadow-yellow-400/15
              active:scale-[0.99] transition">
        Створити замовлення
    </a>

	{{-- Slider: How it works --}}
	<div class="mt-6">
		<div class="flex items-center justify-between mb-3">
			<h2 class="text-white font-bold">Як це працює</h2>
			<span class="text-xs text-gray-400">3 кроки</span>
		</div>

		<div
			x-data="{ i: 0, items: {{ \Illuminate\Support\Js::from($slides) }} }"
			class="relative overflow-hidden rounded-2xl
				   
				   bg-gradient-to-b from-gray-900 to-gray-950"
		>

			{{-- Slide --}}
			<div class="relative w-full h-[560px] overflow-hidden">
				<img
					:src="items[i].image"
					alt=""
					class="absolute inset-0 w-full h-full
						   object-cover
						   select-none pointer-events-none"
				/>

				{{-- Left arrow --}}
				<button
					type="button"
					class="absolute left-2 top-1/2 -translate-y-1/2
						   w-9 h-9 rounded-xl
						   bg-black/40 border border-gray-700
						   text-white text-lg
						   hover:bg-black/60 transition"
					@click="i = (i - 1 + items.length) % items.length"
				>
					‹
				</button>

				{{-- Right arrow --}}
				<button
					type="button"
					class="absolute right-2 top-1/2 -translate-y-1/2
						   w-9 h-9 rounded-xl
						   bg-black/40 border border-gray-700
						   text-white text-lg
						   hover:bg-black/60 transition"
					@click="i = (i + 1) % items.length"
				>
					›
				</button>

			</div>

			{{-- Dots --}}
			<div class="flex justify-center gap-2 py-3">
				<template x-for="(dot, idx) in items" :key="idx">
					<button
						class="h-2 rounded-full transition-all"
						:class="i === idx
							? 'w-8 bg-yellow-400'
							: 'w-2 bg-gray-700 hover:bg-gray-600'"
						@click="i = idx"
					></button>
				</template>
			</div>
		</div>
	</div>

    {{-- Active orders --}}
    <div class="mt-7">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-white font-bold">Активні замовлення</h2>
            <a href="{{ route('client.orders') }}" class="text-xs text-gray-400 hover:text-gray-200">
                Усі →
            </a>
        </div>

        @if($oneTimeOrders->count() === 0 && $subscriptionOrders->count() === 0)
            <div class="rounded-2xl border border-gray-800 bg-gray-900/40 p-5">
                <p class="text-white font-semibold">Немає активних замовлень</p>
                <p class="text-gray-400 text-sm mt-1">Створи перше — і ми знайдемо курʼєра.</p>
            </div>
        @endif

        {{-- One-time orders (cards) --}}
       @if($oneTimeOrders->count())
            <div class="space-y-3">
                 @foreach($oneTimeOrders as $order)
                    <div class="rounded-2xl border border-gray-800 bg-gradient-to-b from-gray-900 to-gray-950 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-gray-400 text-xs">Разовий винос</p>
                                <p class="text-white font-bold mt-1">
                                    Курʼєр винесе сміття
                                    <span class="text-yellow-400">
                                        {{ $order->scheduled_at ? \Carbon\Carbon::parse($order->scheduled_at)->translatedFormat('d M') : 'сьогодні' }}
                                    </span>
                                </p>
                                <p class="text-gray-400 text-sm mt-1">
                                    Статус: <span class="text-gray-200">{{ $order->status }}</span>
                                </p>
                            </div>

                            <a href="{{ route('client.orders') }}"
                               class="shrink-0 w-10 h-10 rounded-xl border border-gray-800 bg-black/20
                                      flex items-center justify-center text-white hover:bg-black/40 transition">
                                →
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Subscription orders (separate block) --}}
       @if($subscriptionOrders->count())
            <div class="mt-4 rounded-2xl border border-gray-800 bg-gray-900/40 p-4">
                <p class="text-white font-bold">Підписка</p>
                <p class="text-gray-400 text-sm mt-1">Ваші регулярні виноси за графіком</p>

                <div class="mt-3 space-y-2">
                     @foreach($subscriptionOrders as $order)
                        <div class="flex items-center justify-between rounded-xl border border-gray-800 bg-black/20 px-3 py-2">
                            <div>
                                <p class="text-gray-300 text-sm font-semibold">
                                    Наступний винос:
                                    <span class="text-yellow-400">
                                        {{ $order->scheduled_at ? \Carbon\Carbon::parse($order->scheduled_at)->translatedFormat('d M, H:i') : 'скоро' }}
                                    </span>
                                </p>
                                <p class="text-gray-500 text-xs">Статус: {{ $order->status }}</p>
                            </div>
                            <a href="{{ route('client.orders') }}" class="text-gray-200 text-sm">Деталі →</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

</div>
