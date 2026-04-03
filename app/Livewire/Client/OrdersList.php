<?php

namespace App\Livewire\Client;

use App\Models\Order;
use Livewire\Component;
use Illuminate\Support\Collection;

class OrdersList extends Component
{
    /** UI */
    public string $tab = 'active'; // active | history

    /** Data */
    public Collection $activeOrders;
    public Collection $historyOrders;
    public ?string $paymentStatus = null;
    public ?int $paymentOrderId = null;
    public bool $showPaymentSuccessModal = false;
    public ?string $cancelFeedback = null;
    public string $cancelFeedbackType = 'info';

    public function mount(): void
    {
        $payment = request()->query('payment');
        $this->paymentStatus = in_array($payment, ['success', 'failed'], true) ? $payment : null;

        $orderId = request()->query('order');
        $this->paymentOrderId = is_numeric($orderId) ? (int) $orderId : null;
        $this->showPaymentSuccessModal = $this->paymentStatus === 'success';

        $this->loadOrders();
    }

    protected function loadOrders(): void
    {
        $userId = auth()->id();
        $excludeSubscriptionExecutions = function ($query): void {
            $query->whereNull('subscription_id')
                ->where(function ($q): void {
                    $q->whereNull('origin')
                        ->orWhere('origin', '!=', Order::ORIGIN_SUBSCRIPTION);
                })
                ->where(function ($q): void {
                    $q->whereNull('order_type')
                        ->orWhere('order_type', '!=', Order::TYPE_SUBSCRIPTION);
                });
        };

        // Активные заказы
        $this->activeOrders = Order::query()
            ->where('client_id', $userId)
            ->where($excludeSubscriptionExecutions)
            ->whereNotIn('status', [
                Order::STATUS_DONE,
                Order::STATUS_CANCELLED,
            ])
            ->orderByDesc('created_at')
            ->get();

        // История заказов
        $this->historyOrders = Order::query()
            ->where('client_id', $userId)
            ->where($excludeSubscriptionExecutions)
            ->whereIn('status', [
                Order::STATUS_DONE,
                Order::STATUS_CANCELLED,
            ])
            ->orderByDesc('created_at')
            ->get();
    }

    /* =========================================================
     |  ACTIONS
     | ========================================================= */

    public function switchTab(string $tab): void
    {
        if (! in_array($tab, ['active', 'history'], true)) {
            return;
        }

        $this->tab = $tab;
    }

    public function dismissPaymentSuccessModal(): void
    {
        $this->showPaymentSuccessModal = false;
    }

    public function cancelOrder(int $orderId): void
    {
        $order = Order::query()
            ->where('id', $orderId)
            ->where('client_id', auth()->id())
            ->first();

        if (! $order) {
            $this->cancelFeedbackType = 'error';
            $this->cancelFeedback = 'Неможливо скасувати це замовлення.';
            $this->dispatch('notify', type: 'error', message: $this->cancelFeedback);
            return;
        }

        if (! $order->canBeCancelled()) {
            $this->cancelFeedbackType = 'error';
            $this->cancelFeedback = 'Це замовлення вже не можна скасувати.';
            $this->dispatch('notify', type: 'error', message: $this->cancelFeedback);
            return;
        }

        if (! $order->cancel()) {
            $this->cancelFeedbackType = 'error';
            $this->cancelFeedback = 'Не вдалося скасувати замовлення. Спробуйте ще раз.';
            $this->dispatch('notify', type: 'error', message: $this->cancelFeedback);
            return;
        }

        $this->cancelFeedbackType = 'success';
        $this->cancelFeedback = "Замовлення #{$order->id} скасовано.";
        $this->dispatch('notify', type: 'success', message: $this->cancelFeedback);
        $this->loadOrders();
    }

    /**
     * 🔁 Повтор заказа из истории
     */
    public function repeatOrder(int $orderId): void
    {
        $order = Order::query()
            ->where('id', $orderId)
            ->where('client_id', auth()->id())
            ->firstOrFail();

        $this->redirectRoute('client.order.create', [
            'address_id' => $order->address_id,
            'repeat'     => $order->id,
        ]);
    }

    public function render()
    {
        return view('livewire.client.orders-list', [
            'activeOrders'  => $this->activeOrders,
            'historyOrders' => $this->historyOrders,
            'tab'           => $this->tab,
        ])->layout('layouts.client');
    }
}
