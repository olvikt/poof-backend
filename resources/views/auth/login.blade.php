<x-layouts.auth>
    <div class="min-h-[100dvh] flex flex-col justify-center items-center px-4 overflow-hidden">
        <x-auth.logo />

        <x-auth.title
            title="Увійти до POOF"
            subtitle="Використайте email або телефон для входу"
        />

        <x-auth.card>
            @if ($errors->any())
                <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
                @csrf

                <input
                    type="text"
                    name="login"
                    value="{{ old('login') }}"
                    placeholder="Email або телефон"
                    required
                    autofocus
                    class="w-full rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-400"
                />

                <input
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    placeholder="Пароль"
                    required
                    class="w-full rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-400"
                />

                <button
                    type="submit"
                    class="w-full bg-yellow-400 text-black font-semibold py-3 rounded-xl hover:bg-yellow-300 transition"
                >
                    Увійти
                </button>
            </form>

            <p class="text-center text-gray-400 text-sm mt-4">
                Забули пароль?
                <a href="{{ url('/forgot-password') }}" class="text-yellow-400 font-semibold">
                    Відновити
                </a>
            </p>

            <p class="text-center text-gray-400 text-sm mt-2">
                Немає акаунту?
                <a href="{{ route('register') }}" class="text-yellow-400 font-semibold">
                    Зареєструватися
                </a>
            </p>
        </x-auth.card>
    </div>
</x-layouts.auth>
