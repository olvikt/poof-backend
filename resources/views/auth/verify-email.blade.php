<x-layouts.auth>
    <x-auth.logo />

    <x-auth.title
        title="Підтвердження email"
        subtitle="Перевірте вашу пошту та підтвердіть адресу email"
    />

    <x-auth.card>
        <form method="POST" action="{{ url('/email/verification-notification') }}" class="space-y-4">
            @csrf

            <button type="submit" class="w-full bg-yellow-400 text-black font-semibold py-3 rounded-xl hover:bg-yellow-300 transition">
                Надіслати лист повторно
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="space-y-4">
            @csrf
            <button type="submit" class="w-full rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-white">
                Вийти
            </button>
        </form>

        <p class="text-center text-gray-400 text-sm mt-2">
            Вже підтвердили email?
            <a href="{{ route('login') }}" class="text-yellow-400 font-semibold">Увійти</a>
        </p>
    </x-auth.card>
</x-layouts.auth>
