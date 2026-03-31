<div x-data="{ aboutOpen: false }" class="px-4 pt-5 pb-[calc(7rem+env(safe-area-inset-bottom))]">
    <section class="mb-5">
        <p class="text-sm text-gray-300">Вітаємо,</p>
        <h1 class="mt-1 text-2xl font-black leading-none text-white">
            {{ auth()->user()->name ?? 'друже' }} 👋
        </h1>
        <p class="mt-2 text-base text-gray-400">Poof — і вже чисто!</p>
    </section>

    <a
        href="{{ route('client.order.create') }}"
        class="block w-full rounded-2xl bg-yellow-400 px-4 py-4 text-center text-xl font-black leading-none text-black shadow-[0_10px_25px_rgba(250,204,21,0.18)] transition active:scale-[0.99]"
    >
        Створити замовлення
    </a>

    <section class="mt-5 grid grid-cols-2 auto-rows-fr gap-3">
        <a href="{{ route('client.profile') }}" class="h-full rounded-2xl border border-white/15 bg-gray-800/95 p-4 min-h-44 flex flex-col shadow-[0_10px_24px_rgba(0,0,0,0.32)] ring-1 ring-white/10">
            <div class="flex items-start justify-between gap-3">
                <h2 class="text-sm font-extrabold leading-none text-white">Мої<br>Адреси</h2>
                <span class="text-yellow-400">
                    <svg class="h-9 w-9" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/>
                    </svg>
                </span>
            </div>
            <div class="mt-auto">
                <p class="text-3xl font-extrabold leading-none text-gray-200">{{ $addressesCount }}</p>
                <p class="mt-1 text-xl text-gray-400">Адрес</p>
            </div>
        </a>

        <a href="{{ route('client.orders') }}" class="h-full rounded-2xl border border-white/15 bg-gray-800/95 p-4 min-h-44 flex flex-col shadow-[0_10px_24px_rgba(0,0,0,0.32)] ring-1 ring-white/10">
            <div class="flex items-start justify-between gap-3">
                <h2 class="text-sm font-extrabold leading-none text-white">Мої<br>Замовлення</h2>
                <span class="text-yellow-400">
                    <svg class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <rect x="4" y="3" width="16" height="18" rx="2"></rect>
                        <path d="M8 8h8M8 12h8M8 16h6"></path>
                    </svg>
                </span>
            </div>
            <div class="mt-auto">
                <p class="text-3xl font-extrabold leading-none text-gray-200">{{ $ordersCount }}</p>
                <p class="mt-1 text-xl text-gray-400">Замовлень</p>
            </div>
        </a>

        <a
            href="https://t.me/poofsupport"
            target="_blank"
            rel="noopener noreferrer"
            class="h-full rounded-2xl border border-white/15 bg-gray-800/95 p-4 min-h-44 flex flex-col shadow-[0_10px_24px_rgba(0,0,0,0.32)] ring-1 ring-white/10"
        >
            <div class="flex items-start justify-between gap-3">
                <h2 class="text-sm font-extrabold leading-none text-white">Тех<br>Підтримка</h2>
                <span class="text-yellow-400">
                    <svg class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path d="M21 14a4 4 0 0 1-4 4H8l-5 4V6a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>
                        <path d="M8 9h8M8 13h6"/>
                    </svg>
                </span>
            </div>
            <p class="mt-auto text-xl text-gray-400">Перейти <span class="text-2xl">→</span></p>
        </a>

        <button
            type="button"
            @click="aboutOpen = true"
            class="h-full rounded-2xl border border-white/15 bg-gray-800/95 p-4 min-h-44 flex flex-col text-left shadow-[0_10px_24px_rgba(0,0,0,0.32)] ring-1 ring-white/10"
        >
            <div class="flex items-start justify-between gap-3">
                <h2 class="text-sm font-extrabold leading-none text-white">Про<br>Сервіс</h2>
                <span class="text-yellow-400">
                    <svg class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 11.5v5M12 7.5h.01"/>
                    </svg>
                </span>
            </div>
            <p class="mt-auto text-xl text-gray-400">Перейти <span class="text-2xl">→</span></p>
        </button>
    </section>

    <form method="POST" action="{{ route('logout') }}" class="mt-6">
        @csrf
        <button
            type="submit"
            class="w-full rounded-2xl border border-rose-500/40 bg-rose-950/60 px-4 py-3 text-xl font-semibold text-rose-300 transition hover:bg-rose-900/70"
        >
            Вийти з акаунту
        </button>
    </form>

    <section class="mt-6 flex flex-col items-center gap-3 pb-2">
        <img src="{{ asset('images/logo-poof.png') }}" alt="Poof" class="h-20 w-20 rounded-2xl object-cover">
        <p class="text-sm text-gray-400">{{ $appVersion }}</p>
    </section>

    <div
        x-show="aboutOpen"
        x-transition.opacity
        class="fixed inset-0 z-40 bg-black/70"
        style="display: none;"
        @click.self="aboutOpen = false"
    >
        <div class="h-full w-full bg-gray-900 text-white overflow-y-auto pb-[calc(2rem+env(safe-area-inset-bottom))]">
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-white/10 bg-gray-900/95 px-4 py-4 backdrop-blur">
                <h3 class="text-lg font-bold">Про сервіс Poof</h3>
                <button type="button" @click="aboutOpen = false" class="rounded-lg border border-white/15 px-3 py-1 text-sm text-gray-300">Закрити</button>
            </div>

            <div class="space-y-4 px-4 py-5 text-base leading-relaxed text-gray-200">
                <p>💡 Як насправді зʼявилась ідея Poof</p>
                <p>Ідея Poof народилась не з бізнес-плану.</p>
                <p>Вона народилась з дуже простої життєвої ситуації.</p>
                <p>Коли хтось із близьких захворів.</p>
                <p>Уявіть ситуацію.</p>
                <p>Поруч немає нікого.</p>
                <p>Людина хвора. Або літня. Або маломобільна.</p>
                <p>А сміття потрібно винести.</p>
                <p>І раптом виявляється, що така проста річ — винести пакет сміття — може бути дуже складною.</p>
                <p>Іноді:</p>
                <ul class="list-disc space-y-1 pl-5 text-gray-300">
                    <li>важко спуститися сходами</li>
                    <li>не працює ліфт</li>
                    <li>до контейнерів далеко</li>
                    <li>немає сил</li>
                    <li>немає кому допомогти</li>
                </ul>
                <p>Для молодої людини це дрібниця. А для літньої — ціла проблема.</p>
                <p>Особливо коли батьки живуть в іншому районі або навіть в іншому місті.</p>
                <p>І ти не можеш просто прийти і допомогти.</p>
                <p>Саме тоді і виникла думка: «А що як зробити сервіс, який допоможе в такій ситуації?»</p>
                <p>Так само просто, як викликати таксі.</p>
                <p>🚀 Так зʼявився Poof</p>
                <p>Poof — це сервіс, який допомагає з простими побутовими речами. Наприклад: винести сміття.</p>
                <p>Але насправді це більше ніж просто сервіс. Це спосіб подбати про близьких.</p>
                <p>❤️ Для кого Poof</p>
                <ul class="list-disc space-y-1 pl-5 text-gray-300">
                    <li>літнім людям</li>
                    <li>маломобільним людям</li>
                    <li>тим, хто хворіє</li>
                    <li>мамам з маленькими дітьми</li>
                    <li>зайнятим людям</li>
                </ul>
                <p>І навіть для тих, хто просто хоче полегшити побут.</p>
                <p>👨‍👩‍👧 Особливо для дітей та онуків</p>
                <p>Poof може стати простим способом допомагати своїм батькам. Навіть якщо ви далеко.</p>
                <p>Наприклад: оформити регулярну допомогу, щоб курʼєр періодично забирав сміття.</p>
                <p>Маленька річ. Але вона може значно полегшити життя літній людині.</p>
                <p>🛠 Ми вже працюємо над сервісом</p>
                <ul class="list-disc space-y-1 pl-5 text-gray-300">
                    <li>✔️ серверна інфраструктура</li>
                    <li>✔️ backend система</li>
                    <li>✔️ система замовлень</li>
                    <li>✔️ dispatch курʼєрів</li>
                    <li>✔️ оптимізація продуктивності</li>
                </ul>
                <p>🌐 Сайт: <a href="https://poof.com.ua" target="_blank" rel="noopener noreferrer" class="text-yellow-400 underline">https://poof.com.ua</a></p>
                <p>Poof — це сервіс, який робить просту річ: допомагає людям піклуватися один про одного.</p>
            </div>
        </div>
    </div>
</div>
