<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#000000">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <title>POOF — швидкий винос сміття</title>
    <link rel="manifest" href="/manifest.json">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="preload"
          as="image"
          href="{{ asset('assets/images/poof3.webp') }}"
          fetchpriority="high">
    <link rel="preload"
          href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800&display=swap"
          as="style"
          onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800&display=swap"
              rel="stylesheet">
    </noscript>
    @vite(['resources/css/app.css','resources/js/app.js'])
    <style>
        .install-banner {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: #111;
            color: white;
            padding: 16px;
            border-radius: 12px;
            z-index: 9999;
            display: none;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
        }

        .banner-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
    </style>
</head>

<body class="bg-zinc-950 text-white font-sans">
	<div class="mx-auto min-h-dvh w-full max-w-md bg-zinc-900">

		<!-- HEADER -->
		<header class="sticky top-0 z-30 border-b border-zinc-800/90 bg-zinc-900/95 backdrop-blur px-4 py-3">
			<div class="flex items-center justify-between">
				<div class="flex items-center gap-2">
					<img src="{{ asset('images/logo-poof.png') }}" class="h-8 w-8 rounded-md" alt="POOF logo" loading="lazy" decoding="async" />
					<span class="text-sm font-extrabold tracking-wide">POOF</span>
				</div>
				<a href="{{ route('login') }}"
				   class="rounded-xl bg-amber-400 px-4 py-2 text-xs font-bold text-zinc-900 hover:bg-amber-300 transition">
					Увійти
				</a>
			</div>
		</header>

		<main class="px-4 pb-12 pt-5 space-y-8">
			 <!-- SLIDER -->
			<section x-data="slider()" x-init="init()" class="mt-3 relative rounded-3xl overflow-hidden shadow-xl">

				<div  x-ref="slider" class="flex overflow-x-auto scroll-smooth snap-x snap-mandatory scroll-smooth"
					@scroll.debounce.50ms="update()">
					<img
					 src="{{ asset('assets/images/poof3.webp') }}"
					 alt="POOF courier"
					 class="min-w-full h-56 object-cover snap-center"
					 loading="eager"
					 fetchpriority="high"
					 decoding="async"
					>
					<img src="{{ asset('assets/images/poof2.webp') }}" class="min-w-full h-56 object-cover snap-center" loading="lazy" decoding="async" alt="POOF service slide">

					<img src="{{ asset('assets/images/poof3.webp') }}" class="min-w-full h-56 object-cover snap-center" loading="lazy" decoding="async" alt="POOF hero slide">

					<img src="{{ asset('assets/images/poof2.webp') }}" class="min-w-full h-56 object-cover snap-center" loading="lazy" decoding="async" alt="POOF service slide">

				</div>

				<!-- стрелка влево -->
				<button @click="prev()"
					class="absolute left-3 top-1/2 -translate-y-1/2 bg-black/50 p-2 rounded-full text-white">
					‹
				</button>

				<!-- стрелка вправо -->
				<button  @click="next()"
					class="absolute right-3 top-1/2 -translate-y-1/2 bg-black/50 p-2 rounded-full text-white">
					›
				</button>

				<!-- точки -->
				<div class="absolute bottom-3 left-0 right-0 flex justify-center gap-2">
					<template x-for="i in total">
						<div class="w-2 h-2 rounded-full" :class="current === i-1 ? 'bg-white' : 'bg-white/40'">
						</div>
					</template>
				</div>

			</section>
			<!-- HERO -->
			<section class="rounded-3xl bg-gradient-to-b from-amber-300 to-amber-500 p-6 text-zinc-900 shadow-xl">
				<p class="text-xs font-semibold uppercase tracking-wide">
					POOF —  перший стартап з України 🇺🇦
				</p>
				<h1 class="mt-2 text-2xl font-extrabold leading-tight">
				   POOF - Швидкий винос сміття як Uber або Bolt, <span class="block">тільки для побутових задач.</span>
				</h1>
			<!-- <p class="mt-2 text-sm font-semibold text-zinc-800"> POOF - і ми Приїхали... Poof — і вже чисто!</p>-->

				<p class="mt-3 text-sm font-medium text-zinc-900">
					Не накопичуй сміття. Не виходь з дому.
					Замовляй курʼєра в кілька тапів.
				</p>

				<div class="mt-5 grid grid-cols-2 gap-2 text-xs font-bold">
					<div class="rounded-2xl bg-zinc-900 px-3 py-2 text-white">⚡ Швидкий виїзд</div>
					<div class="rounded-2xl bg-zinc-900 px-3 py-2 text-white">📍 Live-трекінг</div>
					<div class="rounded-2xl bg-zinc-900 px-3 py-2 text-white">💳 Онлайн оплата</div>
					<div class="rounded-2xl bg-zinc-900 px-3 py-2 text-white">🔁 Підписка</div>
				</div>
			</section>



			<!-- TRUST BLOCK -->
			<section class="grid grid-cols-3 gap-3 text-center text-xs">
				<div class="bg-zinc-800 rounded-2xl p-3">
					<div class="text-lg font-bold text-amber-400">1000+</div>
					Замовлень
				</div>
				<div class="bg-zinc-800 rounded-2xl p-3">
					<div class="text-lg font-bold text-amber-400">4.9★</div>
					Рейтинг
				</div>
				<div class="bg-zinc-800 rounded-2xl p-3">
					<div class="text-lg font-bold text-amber-400">40 хв.</div>
					і ми Приїхали...
				</div>
			</section>

			<!-- HOW IT WORKS -->
			<section 
				x-data="{ open: false }"
				class="rounded-3xl border border-zinc-800 bg-zinc-900 overflow-hidden"
			>

				<!-- HEADER -->
				<button 
					@click="open = !open"
					class="w-full flex items-center justify-between p-5"
				>
					<h2 class="text-base font-bold">Як це працює</h2>

					<svg 
						class="w-5 h-5 transition-transform duration-300"
						:class="open ? 'rotate-180' : ''"
						fill="none" stroke="currentColor" stroke-width="2"
						viewBox="0 0 24 24"
					>
						<path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
					</svg>
				</button>

				<!-- CONTENT -->
				<div 
					x-show="open"
					x-transition
					class="px-5 pb-5"
				>
					<ul class="space-y-4 text-sm text-zinc-300">
						<li>1️⃣ Створіть замовлення</li>
						<li>2️⃣ POOF знаходить найближчого курʼєра</li>
						<li>3️⃣ Передайте сміття — і ви вільні</li>
					</ul>
				</div>

			</section>

			<!-- FOR WHO -->
			<section 
				x-data="{ open: false }"
				class="rounded-3xl border border-zinc-800 bg-zinc-900 overflow-hidden"
			>

				<!-- HEADER -->
				<button 
					@click="open = !open"
					class="w-full flex items-center justify-between p-5"
				>
					<h2 class="text-base font-bold">Для кого POOF</h2>

					<svg 
						class="w-5 h-5 transition-transform duration-300"
						:class="open ? 'rotate-180' : ''"
						fill="none" stroke="currentColor" stroke-width="2"
						viewBox="0 0 24 24"
					>
						<path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
					</svg>
				</button>

				<!-- CONTENT -->
				<div 
					x-show="open"
					x-transition
					class="px-5 pb-5 space-y-4"
				>

					<div class="bg-zinc-800 p-4 rounded-2xl">
						♿ <b>Маломобільним людям</b>
						<p class="text-sm text-zinc-400 mt-1">
							Коли вихід з дому складний — сервіс стає необхідністю.
						</p>
					</div>

					<div class="bg-zinc-800 p-4 rounded-2xl">
						🤒 <b>Тим, хто хворіє</b>
						<p class="text-sm text-zinc-400 mt-1">
							Без зайвих контактів і виходів з дому.
						</p>
					</div>

					<div class="bg-zinc-800 p-4 rounded-2xl">
						❤️ <b>Турбота про близьких</b>
						<p class="text-sm text-zinc-400 mt-1">
							Оформіть підписку для родичів.
						</p>
					</div>

					<div class="bg-zinc-800 p-4 rounded-2xl">
						👶 <b>Мамам у декреті</b>
						<p class="text-sm text-zinc-400 mt-1">
							Коли руки зайняті важливішим.
						</p>
					</div>

					<div class="bg-zinc-800 p-4 rounded-2xl">
						🏢 <b>Бізнесу</b>
						<p class="text-sm text-zinc-400 mt-1">
							Регулярний винос без персоналу.
						</p>
					</div>
				</div>
			</section>

			<!-- SUBSCRIPTION -->
			<section class="rounded-3xl bg-gradient-to-b from-zinc-800 to-zinc-900 p-5 border border-zinc-700">
				<h2 class="text-base font-bold">Підписка POOF</h2>
				<p class="text-sm text-zinc-400 mt-2">
					Автоматичний регулярний винос сміття.
				</p>
				<ul class="mt-4 text-sm space-y-2 text-zinc-300">
					<li>✔ 2–3 рази на тиждень</li>
					<li>✔ Фіксована ціна</li>
					<li>✔ Без нагадувань</li>
				</ul>	
				<a href="{{ route('login') }}"
				   class="mt-5 block w-full rounded-2xl bg-amber-400 py-3 text-center text-sm font-extrabold text-zinc-900 hover:bg-amber-300 transition">
					Почати з POOF
				</a>	   
			</section>
			<!-- CTA -->
			<section class="space-y-3">


			</section>

			<!-- COURIER -->
			<section class="rounded-3xl bg-gradient-to-b from-zinc-800 to-zinc-900 p-5 border border-zinc-700">
				<h2 class="text-base font-bold">Стати курʼєром POOF</h2>
				<p class="text-sm text-zinc-400 mt-2">
					Працюй коли зручно. Заробляй щодня.
				</p>

				<div class="grid grid-cols-2 gap-3 mt-4 text-sm">
					<div class="bg-zinc-800 rounded-xl p-3 text-center">💸 Щоденні виплати</div>
					<div class="bg-zinc-800 rounded-xl p-3 text-center">🕒 Гнучкий графік</div>
					<div class="bg-zinc-800 rounded-xl p-3 text-center">📍 Замовлення поруч</div>
					<div class="bg-zinc-800 rounded-xl p-3 text-center">🚲 Пішки або авто</div>
				</div>
				<a href="/courier/register"
				   class="mt-5 block w-full rounded-2xl bg-amber-400 py-3 text-center text-sm font-extrabold text-zinc-900 hover:bg-amber-300 transition">
					Стати курʼєром
				</a>
			</section>

			<section class="space-y-3">
				<p class="text-center text-xs text-zinc-500">
					POOF — сервіс швидкого виносу сміття в Україні.
				</p>
			</section>

			<button id="installAppBtn" class="w-full rounded-2xl bg-emerald-500 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-400 transition" style="display:none;">
				📱 Встановити додаток
			</button>

		</main>
	</div>

	<div id="installBanner" class="install-banner">
		<div class="banner-content">
			<div class="banner-title">
				🚀 Встановіть додаток POOF
			</div>
			<div class="banner-subtitle">
				Швидше оформляйте замовлення
			</div>
			<div class="banner-buttons">
				<button id="installBannerBtn" class="rounded-xl bg-amber-400 px-4 py-2 font-semibold text-zinc-900">Встановити</button>
				<button id="installBannerClose" class="rounded-xl border border-zinc-600 px-4 py-2">Пізніше</button>
			</div>
		</div>
	</div>
<script>
	window.slider = function () {
		return {
			current: 0,
			total: 0,
			init() {
				this.total = this.$refs.slider?.children?.length ?? 0;
			},
			update() {
				const slider = this.$refs.slider;
				if (!slider) return;
				const width = slider.clientWidth || 1;
				this.current = Math.round(slider.scrollLeft / width);
			},
			next() {
				const slider = this.$refs.slider;
				if (!slider || this.total === 0) return;
				const width = slider.clientWidth;
				const nextIndex = Math.min(this.current + 1, this.total - 1);
				slider.scrollTo({ left: nextIndex * width, behavior: 'smooth' });
				this.current = nextIndex;
			},
			prev() {
				const slider = this.$refs.slider;
				if (!slider || this.total === 0) return;
				const width = slider.clientWidth;
				const prevIndex = Math.max(this.current - 1, 0);
				slider.scrollTo({ left: prevIndex * width, behavior: 'smooth' });
				this.current = prevIndex;
			},
		};
	};

	let deferredPrompt;
	const installAppBtn = document.getElementById('installAppBtn');
	const installBanner = document.getElementById('installBanner');
	const installBannerBtn = document.getElementById('installBannerBtn');
	const installBannerClose = document.getElementById('installBannerClose');
	const isMobile = window.matchMedia('(max-width: 768px)').matches;
	const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;

	window.addEventListener('beforeinstallprompt', (e) => {
		e.preventDefault();
		deferredPrompt = e;

		if (installAppBtn) {
			installAppBtn.style.display = 'block';
		}

		if (installBanner && isMobile && !isStandalone) {
			installBanner.style.display = 'block';
		}
	});

	if (installAppBtn) {
		installAppBtn.addEventListener('click', async () => {
			if (!deferredPrompt) return;

			deferredPrompt.prompt();

			const result = await deferredPrompt.userChoice;
			console.log('Install result:', result);
			deferredPrompt = null;
			installAppBtn.style.display = 'none';
			if (installBanner) {
				installBanner.style.display = 'none';
			}
		});
	}

	if (installBannerBtn) {
		installBannerBtn.addEventListener('click', async () => {
			if (installBanner) {
				installBanner.style.display = 'none';
			}

			if (!deferredPrompt) return;

			deferredPrompt.prompt();
			const result = await deferredPrompt.userChoice;
			console.log('Install result:', result);
			deferredPrompt = null;
			if (installAppBtn) {
				installAppBtn.style.display = 'none';
			}
		});
	}

	if (installBannerClose) {
		installBannerClose.addEventListener('click', () => {
			if (installBanner) {
				installBanner.style.display = 'none';
			}
		});
	}
</script>

</body>
</html>
