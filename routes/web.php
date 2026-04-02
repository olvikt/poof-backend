<?php

use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Client\Payments\DevPaymentController;
use App\Http\Controllers\Client\Payments\PaymentPageController;
use App\Http\Controllers\Client\Payments\PaymentStartController;
use App\Http\Controllers\Client\Payments\WayForPayReturnController;
use App\Http\Controllers\Courier\CourierOrderLifecycleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Pwa\ManifestController;
use App\Livewire\Client\Home;
use App\Livewire\Client\OrderCreate;
use App\Livewire\Client\OrdersList;
use App\Livewire\Client\Profile;
use App\Livewire\Courier\AvailableOrders;
use App\Livewire\Courier\MyOrders;
use App\Models\User;
use App\Support\Auth\PhoneNormalizer;
use App\Support\Auth\RoleEntrypoint;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', function (Request $request) {
    return RoleEntrypoint::detect($request) === RoleEntrypoint::ENTRY_COURIER
        ? view('welcome-courier')
        : view('welcome');
});

Route::get('/readyz', function () {
    return response('ok', 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8')
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
});

Route::get('/manifest-client.json', [ManifestController::class, 'client'])->name('manifest.client');
Route::get('/manifest-courier.json', [ManifestController::class, 'courier'])->name('manifest.courier');

Route::get('/login', fn () => view('auth.login', ['entrypoint' => RoleEntrypoint::ENTRY_CLIENT]))
    ->name('login');

Route::get('/courier/login', fn () => view('auth.login', ['entrypoint' => RoleEntrypoint::ENTRY_COURIER]))
    ->name('login.courier');

Route::get('/register', [RegisterController::class, 'show'])
    ->middleware('guest')
    ->name('register');

Route::get('/courier/register', [RegisterController::class, 'show'])
    ->middleware('guest')
    ->name('courier.register');

Route::view('/forgot-password', 'auth.forgot-password')
    ->middleware('guest')
    ->name('password.request');

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware(['guest', 'throttle:3,1'])
    ->name('password.email');

Route::view('/reset-password/{token}', 'auth.reset-password')
    ->middleware('guest')
    ->name('password.reset');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('guest')
    ->name('password.update');

Route::view('/verify-email', 'auth.verify-email')
    ->middleware('auth')
    ->name('verification.notice');

Route::post('/register', [RegisterController::class, 'register'])
    ->middleware(['guest', 'throttle:10,1'])
    ->name('register.store');

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'login' => ['required', 'string', 'max:255'],
        'password' => ['required'],
    ]);

    $isEmailLogin = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) !== false;
    $identifier = $isEmailLogin ? 'email' : 'phone';
    $loginValue = $isEmailLogin
        ? $credentials['login']
        : PhoneNormalizer::normalize($credentials['login']);

    if (! Auth::attempt([$identifier => $loginValue, 'password' => $credentials['password']])) {
        return back()->withErrors(['login' => 'Невірний email/телефон або пароль']);
    }

    $request->session()->regenerate();

    /** @var User $user */
    $user = auth()->user();
    $nextFromRequest = $request->string('next')->toString();
    $nextFromFallbackCookie = (string) $request->cookie(WayForPayReturnController::LOGIN_FALLBACK_NEXT_COOKIE, '');

    $next = RoleEntrypoint::normalizeNextWithinRoleSpace($nextFromRequest, (string) $user->role)
        ?? RoleEntrypoint::normalizeNextWithinRoleSpace($nextFromFallbackCookie, (string) $user->role);

    $redirect = $next !== null
        ? redirect($next)
        : match (true) {
        $user->isAdmin() => redirect('/admin'),
        $user->isCourier() => redirect()->route('courier.home'),
        default => redirect()->route('client.home'),
    };

    return $redirect->withoutCookie(WayForPayReturnController::LOGIN_FALLBACK_NEXT_COOKIE);
})->name('login.post');

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    $entrypoint = RoleEntrypoint::detect($request);

    return redirect(RoleEntrypoint::loginRouteForEntrypoint($entrypoint));
})->name('logout');

Route::match(['GET', 'POST'], '/payments/wayforpay/return', WayForPayReturnController::class)
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
    ])
    ->name('payments.wayforpay.return');
Route::get('/payments/wayforpay/return/finalize', [WayForPayReturnController::class, 'finalize'])
    ->name('payments.wayforpay.return.finalize');

Route::middleware('auth:web')
    ->prefix('client')
    ->name('client.')
    ->group(function () {
        Route::get('/', Home::class)->name('home');
        Route::get('/order/create', OrderCreate::class)->name('order.create');
        Route::get('/orders', OrdersList::class)->name('orders');
        Route::get('/profile', Profile::class)->name('profile');
        Route::get('/payments/{order}', PaymentPageController::class)->name('payments.show');
        Route::post('/payments/{order}/start', PaymentStartController::class)->name('payments.start');
        Route::post('/payments/dev-pay/{order}', DevPaymentController::class)->name('payments.dev-pay');
    });

Route::middleware('auth:web')
    ->get('/dashboard', fn () => redirect()->route('client.home'))
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::post('/profile/address', [ProfileController::class, 'storeAddress'])->name('profile.address.store');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware('auth:web')
    ->prefix('courier')
    ->name('courier.')
    ->group(function () {
        Route::get('/', fn () => redirect()->route('courier.orders'))->name('home');
        Route::get('/orders', AvailableOrders::class)->name('orders');
        Route::get('/my-orders', MyOrders::class)->name('my-orders');
        Route::post('/orders/{order}/accept', [CourierOrderLifecycleController::class, 'accept'])->name('orders.accept');
        Route::post('/orders/{order}/start', [CourierOrderLifecycleController::class, 'start'])->name('orders.start');
        Route::post('/orders/{order}/complete', [CourierOrderLifecycleController::class, 'complete'])->name('orders.complete');
    });
