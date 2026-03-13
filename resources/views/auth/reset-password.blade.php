<x-layouts.auth>
    <x-auth.logo />

    <x-auth.title
        title="Скинути пароль"
        subtitle="Введіть новий пароль для акаунта"
    />

    <x-auth.card>
        <form method="POST" action="{{ url('/reset-password') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token ?? request()->route('token') }}">

            <input type="email" name="email" value="{{ old('email', request('email')) }}" placeholder="Email" required class="w-full rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-400" />

            <input type="password" name="password" placeholder="Новий пароль" required class="w-full rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-400" />

            <input type="password" name="password_confirmation" placeholder="Підтвердіть пароль" required class="w-full rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-400" />

            <button type="submit" class="w-full bg-yellow-400 text-black font-semibold py-3 rounded-xl hover:bg-yellow-300 transition">
                Оновити пароль
            </button>
        </form>

        <p class="text-center text-gray-400 text-sm mt-4">
            Повернутися до
            <a href="{{ route('login') }}" class="text-yellow-400 font-semibold">входу</a>
        </p>
    </x-auth.card>
</x-layouts.auth>
