@php
    /** @var \App\Models\User|null $authUser */
    $authUser = auth()->user();
    $pendingConfirmations = $authUser && $authUser->isClient()
        ? app(\App\Actions\Orders\Completion\GetPendingConfirmationsForClientAction::class)->handle($authUser)
        : ['count' => 0, 'items' => []];
    $pendingConfirmationsCount = (int) ($pendingConfirmations['count'] ?? 0);
@endphp

<header class="sticky top-0 z-20 border-b border-gray-700/60 bg-gray-900/95 backdrop-blur">
    <div class="mx-auto flex h-14 max-w-md items-center justify-between px-4">
        <a href="{{ route('client.home') }}" class="text-sm font-semibold text-white">Poof Client</a>

        <a href="{{ route('client.subscriptions', ['highlight' => 'awaiting-confirmation']) }}"
           class="relative inline-flex h-10 w-10 items-center justify-center rounded-full transition hover:bg-gray-800 {{ $pendingConfirmationsCount > 0 ? 'text-yellow-300 animate-pulse ring-2 ring-yellow-400/60 ring-offset-2 ring-offset-gray-900' : 'text-gray-200 hover:text-white' }}"
           aria-label="{{ $pendingConfirmationsCount > 0 ? 'Є замовлення, яке потрібно підтвердити' : 'Непідтверджені виконання' }}"
           title="{{ $pendingConfirmationsCount > 0 ? 'Є замовлення, яке потрібно підтвердити' : 'Непідтверджені виконання' }}">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5"/>
                <path d="M9 17a3 3 0 0 0 6 0"/>
            </svg>

            @if($pendingConfirmationsCount > 0)
                <span class="absolute -right-1 -top-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-yellow-400 px-1.5 text-[11px] font-bold text-black shadow-md shadow-yellow-400/40">
                    {{ $pendingConfirmationsCount }}
                </span>
            @endif

            @if($pendingConfirmationsCount > 0)
                <span class="absolute right-1 top-1 h-2.5 w-2.5 rounded-full bg-yellow-300 ring-2 ring-gray-900" aria-hidden="true"></span>
            @endif
        </a>
    </div>
</header>
