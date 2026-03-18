<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ProfileController;

use App\Models\Order;

// Client Livewire
use App\Livewire\Client\Home;
use App\Livewire\Client\OrderCreate;
use App\Livewire\Client\OrdersList;
use App\Livewire\Client\Profile;

// Courier Livewire
use App\Livewire\Courier\AvailableOrders;
use App\Livewire\Courier\MyOrders;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => view('welcome'));

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
| Единый логин для client / courier / admin
|--------------------------------------------------------------------------
*/

// 📄 Login form
Route::get('/login', fn () => view('auth.login'))
    ->name('login');

Route::get('/register', [RegisterController::class, 'show'])
    ->middleware('guest')
    ->name('register');

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

Route::get('/courier/register', fn () => redirect()->route('register', ['role' => 'courier']))
    ->middleware('guest')
    ->name('courier.register');

Route::post('/register', [RegisterController::class, 'register'])
    ->middleware(['guest', 'throttle:10,1'])
    ->name('register.store');

// 🔐 Login submit
Route::post('/login', function (Request $request) {

    $credentials = $request->validate([
        'login'    => ['required', 'string', 'max:255'],
        'password' => ['required'],
    ]);

    $identifier = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL)
        ? 'email'
        : 'phone';

    if (! Auth::attempt([$identifier => $credentials['login'], 'password' => $credentials['password']])) {
        return back()->withErrors([
            'login' => 'Невірний email/телефон або пароль',
        ]);
    }

    $request->session()->regenerate();

    $user = auth()->user();

    // 🔀 Redirect by role
    return match (true) {
        $user->isAdmin()   => redirect('/admin'),
        $user->isCourier() => redirect()->route('courier.orders'),
        default            => redirect()->route('client.home'),
    };

})->name('login.post');


// 🚪 Logout
Route::post('/logout', function (Request $request) {

    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');

})->name('logout');


/*
|--------------------------------------------------------------------------
| Client area
|--------------------------------------------------------------------------
*/

Route::middleware('auth:web')
    ->prefix('client')
    ->name('client.')
    ->group(function () {

        // 🏠 Home
        Route::get('/', Home::class)
            ->name('home');

        // ➕ Create order
        Route::get('/order/create', OrderCreate::class)
            ->name('order.create');

        // 📋 My orders
        Route::get('/orders', OrdersList::class)
            ->name('orders');

        // 👤 Profile
        Route::get('/profile', Profile::class)
            ->name('profile');

        /*
        |--------------------------------------------------------------------------
        | Payments (TEMP / MVP)
        |--------------------------------------------------------------------------
        */

        Route::get('/payments/pay/{order}', function (Order $order) {

            abort_if($order->client_id !== auth()->id(), 403);

            if ($order->payment_status === Order::PAY_PAID) {
                return redirect()
                    ->route('client.orders')
                    ->with('success', 'Замовлення вже оплачено.');
            }

            return view('payments.pay', [
                'order' => $order,
            ]);

        })->name('payments.pay');


        Route::post('/payments/dev-pay/{order}', function (Order $order) {

            abort_if($order->client_id !== auth()->id(), 403);

            if ($order->payment_status === Order::PAY_PAID) {
                return redirect()->route('client.orders');
            }

            $order->markAsPaid();

            return redirect()
                ->route('client.orders')
                ->with('success', 'Оплата успішна. Ми шукаємо курʼєра.');

        })->name('payments.dev-pay');
    });

Route::middleware('auth:web')
    ->get('/dashboard', fn () => redirect()->route('client.home'))
    ->name('dashboard');


Route::middleware('auth')->group(function () {
    Route::post('/profile/address', [ProfileController::class, 'storeAddress'])
        ->name('profile.address.store');

    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])
        ->name('profile.avatar.update');

    Route::post('/profile/update', [ProfileController::class, 'update'])
        ->name('profile.update');
});

/*
|--------------------------------------------------------------------------
| Courier area
|--------------------------------------------------------------------------
*/

Route::middleware('auth:web')
    ->prefix('courier')
    ->name('courier.')
    ->group(function () {

        Route::get('/', fn () => redirect()->route('courier.orders'))
            ->name('home');

        // 📦 Available orders
        Route::get('/orders', AvailableOrders::class)
            ->name('orders');

        // 🚴‍♂️ My active orders
        Route::get('/my-orders', MyOrders::class)
            ->name('my-orders');

        // ✅ Accept
        Route::post('/orders/{order}/accept', function (Order $order) {

            abort_if(! auth()->user()?->isCourier(), 403);

            $ok = $order->acceptBy(auth()->user());

            return $ok
                ? redirect()->route('courier.my-orders')->with('success', 'Замовлення прийнято.')
                : back()->with('error', 'Не вдалося прийняти замовлення.');

        })->name('orders.accept');


        // ▶️ Start
        Route::post('/orders/{order}/start', function (Order $order) {

            abort_if(! auth()->user()?->isCourier(), 403);
            abort_if($order->courier_id !== auth()->id(), 403);

            if (! $order->canBeStarted()) {
                return back()->with('error', 'Неможливо розпочати це замовлення.');
            }

            $order->startBy(auth()->user());

            return redirect()
                ->route('courier.my-orders')
                ->with('success', 'Замовлення розпочато.');

        })->name('orders.start');


        // ✅ Complete
        Route::post('/orders/{order}/complete', function (Order $order) {

            abort_if(! auth()->user()?->isCourier(), 403);
            abort_if($order->courier_id !== auth()->id(), 403);

            if (! $order->canBeCompleted()) {
                return back()->with('error', 'Неможливо завершити це замовлення.');
            }

            $order->completeBy(auth()->user());

            return redirect()
                ->route('courier.my-orders')
                ->with('success', 'Замовлення завершено.');

        })->name('orders.complete');
    });
