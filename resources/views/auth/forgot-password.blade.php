<x-layouts.auth>
    <div class="min-h-[100dvh] flex flex-col justify-center items-center px-4 overflow-hidden">
        <x-auth.logo />

        <x-auth.title
            title="Відновлення пароля"
            subtitle="Ми надішлемо посилання для скидання на ваш email"
        />

        <x-auth.card>
            <form method="POST" action="{{ url('/forgot-password') }}" class="space-y-4">
                @csrf

                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="Email"
                    required
                    class="w-full rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-400"
                />

                <button type="submit" class="w-full bg-yellow-400 text-black font-semibold py-3 rounded-xl hover:bg-yellow-300 transition">
                    Надіслати посилання
                </button>
            </form>

            <p class="text-center text-gray-400 text-sm mt-4">
                Згадали пароль?
                <a href="{{ route('login') }}" class="text-yellow-400 font-semibold">Увійти</a>
            </p>
        </x-auth.card>
    </div>
</x-layouts.auth>
