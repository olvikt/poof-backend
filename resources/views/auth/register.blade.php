<x-layouts.app title="Реєстрація — Poof">
    <div class="min-h-screen bg-gradient-to-b from-neutral-900 to-black text-white px-4 py-8 sm:py-12">
        <div class="mx-auto w-full max-w-md" x-data="{
            role: '{{ old('role', $defaultRole) }}',
            transportType: '{{ old('transport_type') }}',
            city: '{{ old('city') }}',
            termsAgreed: {{ old('terms_agreed') ? 'true' : 'false' }},
            onRoleChange(nextRole) {
                this.role = nextRole;

                if (this.role === 'client') {
                    this.transportType = '';
                    this.city = '';
                    this.termsAgreed = false;
                }
            },
        }">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center overflow-hidden rounded-[22px] bg-yellow-400 shadow-[0_0_30px_rgba(250,204,21,0.45)]">
                    <img src="/images/logo-poof.png" alt="Poof logo" class="h-12 w-12 object-contain">
                </div>
                <h1 class="text-2xl font-extrabold tracking-tight">Створіть акаунт</h1>
                <p class="mt-1 text-sm text-white/60">Кілька кроків — і можна робити перші замовлення</p>
            </div>

            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                    <ul class="space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register.store') }}" class="space-y-4 rounded-2xl border border-white/10 bg-white/5 p-4 sm:p-5">
                @csrf

                <div class="grid grid-cols-2 gap-2 rounded-xl bg-white/5 p-1">
                    <button
                        type="button"
                        @click="onRoleChange('client')"
                        :class="role === 'client' ? 'bg-yellow-400 text-black' : ''"
                        class="rounded-lg px-4 py-3 text-sm font-semibold text-white/70 transition"
                    >
                        Клієнт
                    </button>
                    <button
                        type="button"
                        @click="onRoleChange('courier')"
                        :class="role === 'courier' ? 'bg-yellow-400 text-black' : ''"
                        class="rounded-lg px-4 py-3 text-sm font-semibold text-white/70 transition"
                    >
                        Курʼєр
                    </button>
                </div>

                <input type="hidden" name="role" x-model="role">

                <input
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    placeholder="Ім’я"
                    required
                    class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-yellow-400"
                >

                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="Email"
                    required
                    class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-yellow-400"
                >

                <div class="flex gap-2">
                    <select
                        name="country_code"
                        class="w-28 rounded-xl border border-white/10 bg-white/5 px-3 py-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400"
                    >
                        <option value="+380" @selected(old('country_code', '+380') === '+380') class="text-black">🇺🇦 +380</option>
                    </select>

                    <input
                        type="tel"
                        name="phone"
                        value="{{ old('phone') }}"
                        placeholder="99 111 11 11"
                        required
                        class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-yellow-400"
                    >
                </div>

                <input
                    type="password"
                    name="password"
                    placeholder="Пароль"
                    required
                    class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-yellow-400"
                >

                <input
                    type="password"
                    name="password_confirmation"
                    placeholder="Підтвердження пароля"
                    required
                    class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-yellow-400"
                >

                <div x-show="role === 'courier'" x-transition x-cloak class="space-y-4 rounded-xl border border-white/10 bg-black/30 p-4">
                    <select
                        name="transport_type"
                        x-model="transportType"
                        class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400"
                    >
                        <option value="" class="text-black">Тип транспорту</option>
                        <option value="walk" @selected(old('transport_type') === 'walk') class="text-black">Пішки</option>
                        <option value="bike" @selected(old('transport_type') === 'bike') class="text-black">Велосипед</option>
                        <option value="scooter" @selected(old('transport_type') === 'scooter') class="text-black">Скутер</option>
                        <option value="car" @selected(old('transport_type') === 'car') class="text-black">Автомобіль</option>
                    </select>

                    <input
                        type="text"
                        name="city"
                        x-model="city"
                        placeholder="Місто"
                        class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-yellow-400"
                    >

                    <label class="flex items-start gap-3 text-sm text-white/80">
                        <input type="checkbox" name="terms_agreed" value="1" x-model="termsAgreed" @checked(old('terms_agreed')) class="mt-1 h-4 w-4 rounded border-white/20 text-yellow-400 focus:ring-yellow-400">
                        <span>Підтверджую, що погоджуюсь з умовами та правилами платформи POOF</span>
                    </label>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-xl bg-yellow-400 py-3 text-base font-bold text-black transition active:scale-95"
                >
                    Зареєструватися
                </button>

                <div class="text-center mt-6">
                    <span class="text-gray-400">Вже є акаунт?</span>
                    <a href="{{ route('login') }}" class="text-yellow-400 hover:underline">Увійти</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
