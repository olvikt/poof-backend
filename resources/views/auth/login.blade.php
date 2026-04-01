<x-layouts.auth>
    @php($courierRegisterUrl = 'https://courier.poof.com.ua/register')

    <div class="min-h-[100dvh] flex flex-col justify-center items-center px-4 overflow-hidden">
        <x-auth.logo />

        <x-auth.title
            title="{{ ($entrypoint ?? 'client') === 'courier' ? 'Увійти як курʼєр' : 'Увійти як клієнт' }}"
            subtitle="{{ ($entrypoint ?? 'client') === 'courier' ? 'Курʼєрський role-space POOF' : 'Клієнтський role-space POOF' }}"
        />

        <x-auth.card>
            @if ($errors->any())
                <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="next" value="{{ request('next') }}">

                <input type="text" name="login" value="{{ old('login') }}" placeholder="Email або телефон" required autofocus class="w-full rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-400" />
                <div x-data="{ show: false }" class="relative">
                    <input x-bind:type="show ? 'text' : 'password'" name="password" autocomplete="current-password" placeholder="Пароль" required class="w-full rounded-xl bg-white/10 border border-white/10 px-4 py-3 pr-12 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-400" />

                    <button type="button" @click="show = !show" :aria-label="show ? 'Сховати пароль' : 'Показати пароль'" class="absolute inset-y-0 right-0 flex items-center px-4 text-gray-300 transition hover:text-white">
                        <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7Z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.585 10.586A2 2 0 0 0 13.414 13.414" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.09A10.94 10.94 0 0 1 12 5c4.478 0 8.268 2.943 9.542 7a11.08 11.08 0 0 1-4.16 5.408M6.61 6.61A11.06 11.06 0 0 0 2.458 12c1.274 4.057 5.065 7 9.542 7a10.94 10.94 0 0 0 2.12-.208" />
                        </svg>
                    </button>
                </div>

                <button type="submit" class="w-full bg-yellow-400 text-black font-semibold py-3 rounded-xl hover:bg-yellow-300 transition">Увійти</button>
            </form>

            <p class="text-center text-gray-400 text-sm mt-4">Забули пароль? <a href="{{ url('/forgot-password') }}" class="text-yellow-400 font-semibold">Відновити</a></p>

            <div class="mt-4 rounded-xl border border-white/10 bg-white/5 p-4 text-sm text-gray-300">
                @if (($entrypoint ?? 'client') === 'courier')
                    Потрібен клієнтський акаунт?
                    <a href="{{ $courierRegisterUrl }}" class="font-semibold text-yellow-400">Клієнтська реєстрація</a>
                @else
                    Хочете стати курʼєром?
                    <a href="{{ $courierRegisterUrl }}" class="font-semibold text-yellow-400">Курʼєрська реєстрація</a>
                @endif
            </div>
        </x-auth.card>
    </div>
</x-layouts.auth>
