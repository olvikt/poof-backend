<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

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

// 🔐 Login submit
Route::post('/login', function (Request $request) {

    $credentials = $request->validate([
        'email'    => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (! Auth::attempt($credentials)) {
        return back()->withErrors([
            'email' => 'Невірний email або пароль',
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


// 🚪 Logout (POST — остаётся как есть)

Route::get('/logout', function () {

    // если сессия жива
    if (Auth::check()) {
        Auth::logout();
    }

    // никаких invalidate() и regenerateToken()

    return redirect('/login');

})->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
  ->name('logout');


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

/*
|--------------------------------------------------------------------------
| Courier area
|--------------------------------------------------------------------------
*/

Route::middleware('auth:web')
    ->prefix('courier')
    ->name('courier.')
    ->group(function () {

        // 📦 Available orders
        Route::get('/orders', AvailableOrders::class)
            ->name('orders');

        // 🚴‍♂️ My active orders
        Route::get('/my-orders', MyOrders::class)
            ->name('my-orders');

        // ✅ Accept
        Route::post('/orders/{order}/accept', function (Order $order) {

            abort_if(! auth()->user()?->isCourier(), 403);

            if (! $order->canBeAccepted()) {
                return back()->with('error', 'Замовлення вже прийнято або недоступне.');
            }

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

            $order->start();

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

            $order->complete();

            return redirect()
                ->route('courier.my-orders')
                ->with('success', 'Замовлення завершено.');

        })->name('orders.complete');
    });