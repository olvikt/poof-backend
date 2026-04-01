<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#000000">
    <title>POOF Courier</title>
    <link rel="manifest" href="{{ route('manifest.courier') }}">
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="bg-zinc-950 text-white font-sans">
<div class="mx-auto min-h-dvh w-full max-w-md bg-zinc-900 px-4 py-6 space-y-6">
    <header class="flex items-center justify-between">
        <h1 class="text-lg font-extrabold">POOF Courier</h1>
        <a href="{{ route('login.courier') }}" class="rounded-xl bg-amber-400 px-4 py-2 text-xs font-bold text-zinc-900">Увійти як курʼєр</a>
    </header>

    <section class="rounded-3xl bg-gradient-to-b from-amber-300 to-amber-500 p-6 text-zinc-900 shadow-xl">
        <p class="text-xs font-semibold uppercase tracking-wide">Окремий курʼєрський контур</p>
        <h2 class="mt-2 text-2xl font-extrabold leading-tight">Доставляйте замовлення та заробляйте з POOF.</h2>
        <p class="mt-3 text-sm font-medium text-zinc-900">Окремий login, окрема реєстрація, окремий install flow для курʼєра.</p>
        <a href="{{ route('courier.register') }}" class="mt-5 block w-full rounded-2xl bg-zinc-900 py-3 text-center text-sm font-extrabold text-white">Стати курʼєром</a>
    </section>

    <section class="rounded-3xl border border-zinc-700 bg-zinc-800/60 p-5">
        <h3 class="font-bold">Хочете стати клієнтом?</h3>
        <p class="text-sm text-zinc-300 mt-2">Клієнтський застосунок POOF працює на окремому домені, але всередині однієї екосистеми.</p>
        <a href="https://app.poof.com.ua" class="mt-4 inline-block text-sm font-semibold text-amber-400">Перейти на app.poof.com.ua</a>
    </section>

    <button id="installAppBtn" class="w-full rounded-2xl bg-emerald-500 px-4 py-3 text-sm font-bold text-white" style="display:none;">📱 Встановити курʼєрський застосунок</button>
</div>
<script>
let deferredPrompt;
const installAppBtn = document.getElementById('installAppBtn');
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    if (installAppBtn) installAppBtn.style.display = 'block';
});
if (installAppBtn) {
    installAppBtn.addEventListener('click', async () => {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        deferredPrompt = null;
        installAppBtn.style.display = 'none';
    });
}
</script>
</body>
</html>
