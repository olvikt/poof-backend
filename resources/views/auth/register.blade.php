<x-layouts.auth>
    <div class="min-h-[100dvh] flex flex-col items-center px-4 pt-12 pb-12">
        <x-auth.logo />

        <x-auth.title
            title="Створіть акаунт"
            subtitle="Кілька кроків — і можна робити перші замовлення"
        />

        <x-auth.card>
        @if ($errors->any())
            <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register.store') }}" class="space-y-4" x-data="{ role: '{{ old('role', $defaultRole) }}', activeTab: 'bg-yellow-400 text-black shadow-sm', inactiveTab: 'text-white/70 hover:text-white', transport_type: '{{ old('transport_type') }}', city: '{{ old('city') }}' }" x-effect="if(role === 'client'){ transport_type = ''; city = '' }">
            @csrf

            <div class="grid grid-cols-2 gap-2 rounded-xl bg-white/5 p-1">
                <button type="button" @click="role = 'client'" :class="role === 'client' ? activeTab : inactiveTab" class="rounded-lg px-4 py-3 text-sm font-semibold transition">
                    Клієнт
                </button>
                <button type="button" @click="role = 'courier'" :class="role === 'courier' ? activeTab : inactiveTab" class="rounded-lg px-4 py-3 text-sm font-semibold transition">
                    Курʼєр
                </button>
            </div>

            <input type="hidden" name="role" x-model="role">

            <input type="text" name="name" autocomplete="name" value="{{ old('name') }}" placeholder="Ім’я" required class="w-full rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-400" />

            <input type="email" name="email" autocomplete="email" value="{{ old('email') }}" placeholder="Email" required class="w-full rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-400" />

            <div class="flex gap-2">
                <select name="country_code" class="w-28 rounded-xl bg-white/10 border border-white/10 px-3 py-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400">
                    <option value="+380" @selected(old('country_code', '+380') === '+380') class="text-black">🇺🇦 +380</option>
                </select>

                <input type="tel" name="phone" autocomplete="tel" value="{{ old('phone') }}" placeholder="99 111 11 11" required class="w-full rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-400" />
            </div>

            <input type="password" name="password" autocomplete="new-password" placeholder="Пароль" required class="w-full rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-400" />

            <input type="password" name="password_confirmation" autocomplete="new-password" placeholder="Підтвердження пароля" required class="w-full rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-400" />

            <div x-show="role === 'courier'" x-transition x-cloak class="space-y-4 rounded-xl border border-white/10 bg-black/30 p-4">
                <select name="transport_type" x-model="transport_type" class="w-full rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400">
                    <option value="" class="text-black">Тип транспорту</option>
                    <option value="walk" @selected(old('transport_type') === 'walk') class="text-black">Пішки</option>
                    <option value="bike" @selected(old('transport_type') === 'bike') class="text-black">Велосипед</option>
                    <option value="scooter" @selected(old('transport_type') === 'scooter') class="text-black">Скутер</option>
                    <option value="car" @selected(old('transport_type') === 'car') class="text-black">Автомобіль</option>
                </select>

                <input type="text" name="city" x-model="city" placeholder="Місто" class="w-full rounded-xl bg-white/10 border border-white/10 px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-400" />
            </div>

            <div class="flex items-start gap-3">
                <input type="checkbox" name="terms_agreed" value="1" @checked(old('terms_agreed')) required class="mt-1 h-4 w-4 rounded border-white/20 text-yellow-400 focus:ring-yellow-400" />
                <label class="text-sm text-gray-300">
                    Підтверджую, що погоджуюсь з умовами та правилами платформи POOF
                </label>
            </div>

            <button type="submit" class="w-full bg-yellow-400 text-black font-semibold py-3 rounded-xl hover:bg-yellow-300 transition">
                Зареєструватися
            </button>
        </form>

        <p class="text-center text-gray-400 text-sm mt-4">
            Вже є акаунт?
            <a href="{{ route('login') }}" class="text-yellow-400 font-semibold">
                Увійти
            </a>
        </p>
        </x-auth.card>
    </div>
</x-layouts.auth>
