<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

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

        $ok = $order->acceptBy($courier);

        return $ok
            ? redirect()->route('courier.my-orders')->with('success', 'Замовлення прийнято.')
            : back()->with('error', 'Не вдалося прийняти замовлення.');
    }

    public function start(Order $order): RedirectResponse
    {
        $courier = $this->resolveCourier();
        abort_if(! $courier instanceof User, 403);
        abort_if((int) $order->courier_id !== (int) $courier->id, 403);

        if (! $order->canBeStarted()) {
            return back()->with('error', 'Неможливо розпочати це замовлення.');
        }

        $order->startBy($courier);

        return redirect()
            ->route('courier.my-orders')
            ->with('success', 'Замовлення розпочато.');
    }

    public function complete(Order $order): RedirectResponse
    {
        $courier = $this->resolveCourier();
        abort_if(! $courier instanceof User, 403);
        abort_if((int) $order->courier_id !== (int) $courier->id, 403);

        if (! $order->canBeCompleted()) {
            return back()->with('error', 'Неможливо завершити це замовлення.');
        }

        $order->completeBy($courier);

        return redirect()
            ->route('courier.my-orders')
            ->with('success', 'Замовлення завершено.');
    }

    private function resolveCourier(): ?User
    {
        $courier = auth()->user();

        return $courier instanceof User && $courier->isCourier()
            ? $courier
            : null;
    }
}
