<?php

declare(strict_types=1);

namespace App\Livewire\Client;

use App\Actions\Orders\Completion\GetOrderCompletionClientPayloadAction;
use App\Models\Order;
use Illuminate\Support\Collection;
use Livewire\Component;

class OrderProofsPage extends Component
{
    public Order $order;

    /** @var Collection<int, string> */
    public Collection $proofUrls;

    public function mount(Order $order): void
    {
        abort_unless((int) $order->client_id === (int) auth()->id(), 403);

        $this->order = $order;

        $payload = app(GetOrderCompletionClientPayloadAction::class)->handle($order, auth()->user(), false);

        $this->proofUrls = collect($payload['proofs'] ?? [])
            ->pluck('url')
            ->filter(fn ($url) => is_string($url) && $url !== '')
            ->values();
    }

    public function render()
    {
        return view('livewire.client.order-proofs-page')
            ->layout('layouts.client');
    }
}
