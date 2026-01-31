<x-layouts.app title="Вхід — Poof">

    <div class="min-h-screen bg-gradient-to-b from-neutral-900 to-black text-white flex items-center justify-center px-4">

        <div class="w-full max-w-sm">

            {{-- LOGO --}}
           <div class="flex flex-col items-center mb-8">
				<div
					class="
						w-20 h-20 mb-4
						rounded-[22px]
						bg-yellow-400
						flex items-center justify-center
						overflow-hidden
						shadow-[0_0_30px_rgba(250,204,21,0.45)]
						animate-[logo-pop_.6s_ease-out]
					"
				>
					<img
						src="/images/logo-poof.png"
						alt="Poof logo"
						class="w-12 h-12 object-contain"
					>
				</div>

				<h1 class="text-2xl font-extrabold tracking-tight">
					Poof
				</h1>

				<p class="text-white/60 text-sm mt-1">
					Увійдіть у свій акаунт
				</p>
			</div>


            {{-- ERRORS --}}
            @if ($errors->any())
                <div class="mb-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- FORM --}}
            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <input
                    type="email"
                    name="email"
                    placeholder="Email"
                    required
                    autofocus
                    class="w-full rounded-xl bg-white/5 border border-white/10 px-4 py-3 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-yellow-400"
                >

                <input
                    type="password"
                    name="password"
                    placeholder="Пароль"
                    required
                    class="w-full rounded-xl bg-white/5 border border-white/10 px-4 py-3 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-yellow-400"
                >

                <button
                    type="submit"
                    class="w-full mt-2 bg-yellow-400 text-black font-bold py-3 rounded-xl active:scale-95 transition"
                >
                    Увійти
                </button>
            </form>

            {{-- FOOTER --}}
            <div class="mt-6 text-center text-sm text-white/40">
                Poof — і вже чисто ✨
            </div>

        </div>
    </div>

</x-layouts.app>