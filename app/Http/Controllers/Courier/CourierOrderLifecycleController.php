<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Actions\Orders\Lifecycle\AcceptOrderByCourierAction;
use App\Actions\Orders\Lifecycle\CompleteOrderByCourierAction;
use App\Actions\Orders\Lifecycle\StartOrderByCourierAction;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CourierOrderLifecycleController extends Controller
{
    public function accept(Order $order): RedirectResponse
    {
        $courier = $this->resolveCourier();
        abort_if(! $courier instanceof User, 403);

        $ok = app(AcceptOrderByCourierAction::class)->handle($order, $courier);

        return $ok
            ? redirect()->route('courier.my-orders')->with('success', 'Замовлення прийнято.')
            : back()->with('error', 'Не вдалося прийняти замовлення.');
    }

    public function start(Order $order): RedirectResponse
    {
        $courier = $this->resolveCourier();
        abort_if(! $courier instanceof User, 403);

        $ok = app(StartOrderByCourierAction::class)->handle($order, $courier);

        return $ok
            ? redirect()->route('courier.my-orders')->with('success', 'Замовлення розпочато.')
            : back()->with('error', 'Неможливо розпочати це замовлення.');
    }

    public function complete(Order $order): RedirectResponse
    {
        $courier = $this->resolveCourier();
        abort_if(! $courier instanceof User, 403);

        $ok = app(CompleteOrderByCourierAction::class)->handle($order, $courier);

        return $ok
            ? redirect()->route('courier.my-orders')->with('success', 'Замовлення завершено.')
            : back()->with('error', 'Неможливо завершити це замовлення.');
    }

    private function resolveCourier(): ?User
    {
        $courier = auth()->user();

        return $courier instanceof User && $courier->isCourier()
            ? $courier
            : null;
    }
}
