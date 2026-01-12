<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <title>Poof — Кабінет курʼєра</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    @livewireStyles
</head>
<body style="font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#f9fafb">

    {{-- Header --}}
    <header style="background:#111827;color:#fff;padding:12px 20px">
        <div style="max-width:1000px;margin:0 auto;display:flex;justify-content:space-between;align-items:center">
            <strong>🚴 Poof · Курʼєр</strong>

            <nav style="display:flex;gap:14px">
                <a href="{{ route('courier.orders') }}"
                   style="color:#fff;text-decoration:none">
                    Доступні
                </a>

                <a href="{{ route('courier.my-orders') }}"
                   style="color:#fff;text-decoration:none">
                    Мої замовлення
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button style="background:none;border:none;color:#fff;cursor:pointer">
                        Вийти
                    </button>
                </form>
            </nav>
        </div>
    </header>

    {{-- Page content --}}
    <main style="padding:20px">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
