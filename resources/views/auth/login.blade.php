<x-layouts.app title="Вхід — Poof">
    <div class="min-h-screen bg-gradient-to-b from-neutral-900 to-black text-white px-4 py-8 sm:py-12">
        <div class="mx-auto w-full max-w-md">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center overflow-hidden rounded-[22px] bg-yellow-400 shadow-[0_0_30px_rgba(250,204,21,0.45)]">
                    <img src="/images/logo-poof.png" alt="Poof logo" class="w-12 object-contain">
                </div>
                <h1 class="text-2xl font-extrabold tracking-tight">Увійти до POOF</h1>
                <p class="mt-1 text-sm text-white/60">Використайте email або телефон для входу</p>
            </div>

            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4 rounded-2xl border border-white/10 bg-white/5 p-4 sm:p-5">
                @csrf

                <input
                    type="text"
                    name="login"
                    value="{{ old('login') }}"
                    placeholder="Email або телефон"
                    required
                    autofocus
                    class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-yellow-400"
                >

                <input
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    placeholder="Пароль"
                    required
                    class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-yellow-400"
                >

                <button
                    type="submit"
                    class="w-full rounded-xl bg-yellow-400 py-3 text-base font-bold text-black transition active:scale-95"
                >
                    Увійти
                </button>

                <div class="mt-6 text-center text-sm">
                    <span class="text-white/60">Немає акаунту?</span>
                    <a href="{{ route('register') }}" class="font-semibold text-yellow-400 hover:underline">Зареєструватися</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
