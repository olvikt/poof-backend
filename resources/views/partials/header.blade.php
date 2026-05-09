@php
    /** @var \App\Models\User|null $authUser */
    $authUser = auth()->user();
    $pendingConfirmations = $authUser && $authUser->isClient()
        ? app(\App\Actions\Orders\Completion\GetPendingConfirmationsForClientAction::class)->handle($authUser)
        : ['count' => 0, 'items' => []];
    $pendingConfirmationsCount = (int) ($pendingConfirmations['count'] ?? 0);
@endphp

<header class="sticky top-0 z-20 border-b border-gray-700/60 bg-gray-900/95 backdrop-blur">
    <div class="mx-auto flex h-14 max-w-md items-center justify-between px-4" x-data="{ openConfirmationsMenu: false }" @click.outside="openConfirmationsMenu = false">
        @if($pendingConfirmationsCount > 0)
            <div data-e2e="debug-pending-count" class="sr-only">{{ $pendingConfirmationsCount }}</div>
        @endif
        <a href="{{ route('client.home') }}" class="text-sm font-semibold text-white">Poof Client</a>

        <div class="relative">
        @if($pendingConfirmationsCount > 0)
        <button type="button"
           @click="openConfirmationsMenu = !openConfirmationsMenu"
           :aria-expanded="openConfirmationsMenu"
           aria-controls="client-confirmation-bell-menu"
           data-e2e="client-confirmation-bell-active" class="relative inline-flex h-10 w-10 items-center justify-center rounded-full text-yellow-300 ring-2 ring-yellow-400/60 ring-offset-2 ring-offset-gray-900 transition hover:bg-gray-800"
           aria-label="Є {{ $pendingConfirmationsCount }} замовлень, які потрібно підтвердити"
           title="Є замовлення, яке потрібно підтвердити">
        @else
        <a href="{{ route('client.subscriptions', ['highlight' => 'awaiting-confirmation']) }}"
           class="relative inline-flex h-10 w-10 items-center justify-center rounded-full transition hover:bg-gray-800 text-gray-200 hover:text-white"
           aria-label="Непідтверджені виконання"
           title="Непідтверджені виконання">
        @endif
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
        @if($pendingConfirmationsCount > 0)
        </button>

        <div
            x-show="openConfirmationsMenu"
            x-cloak
            id="client-confirmation-bell-menu"
            data-e2e="client-confirmation-bell-menu"
            class="absolute right-0 top-full z-50 mt-2 w-[min(22rem,calc(100vw-1rem))] origin-top-right overflow-hidden rounded-xl border border-yellow-400/40 bg-neutral-900 p-3 shadow-2xl"
        >
            <p class="text-sm font-semibold text-yellow-300">Потрібно підтвердити</p>
            <p class="mt-1 text-xs text-gray-300">Кур’єр позначив виконання. Підтвердіть замовлення.</p>

            <div class="mt-3 space-y-2">
                @foreach(collect($pendingConfirmations['items'] ?? [])->take(5) as $item)
                    <div data-e2e="client-confirmation-bell-item" class="rounded-lg border border-gray-700 bg-gray-800/70 p-2">
                        <div class="text-sm font-medium text-white">{{ $item['title'] ?? '' }}</div>
                        @if(!empty($item['subtitle']))
                            <div class="mt-0.5 line-clamp-2 text-xs text-gray-400">{{ $item['subtitle'] }}</div>
                        @endif
                        <a href="{{ $item['target_url'] ?? '#' }}" class="mt-2 inline-flex text-xs font-semibold text-yellow-300 hover:text-yellow-200">{{ $item['target_label'] ?? 'Перейти' }}</a>
                    </div>
                @endforeach
            </div>
        </div>
        @else
        </a>
        @endif
        </div>
    </div>
</header>
