<?php

declare(strict_types=1);

namespace App\Actions\Orders\Completion;

use App\Models\Order;
use App\Models\OrderCompletionRequest;
use App\Models\User;
use App\Services\Orders\Completion\OrderCompletionProofMediaUrlResolver;
use Illuminate\Support\Facades\Log;

class GetOrderCompletionClientPayloadAction
{
    public function __construct(private readonly OrderCompletionProofMediaUrlResolver $mediaUrlResolver)
    {
    }

    public function handle(Order $order, User $client): ?array
    {
        if ((int) $order->client_id !== (int) $client->id) {
            return null;
        }

        $request = OrderCompletionRequest::query()->with(['proofs', 'courier'])->where('order_id', $order->id)->first();
        if (! $request) {
            return null;
        }

        $isAwaiting = $request->status === OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION;

        Log::info('completion_client_payload_viewed', [
            'flow' => 'order_completion_proof',
            'order_id' => $order->id,
            'completion_request_id' => $request->id,
            'client_id' => $client->id,
        ]);

        return [
            'order_id' => $order->id,
            'completion_request_id' => $request->id,
            'status' => $request->status,
            'submitted_at' => optional($request->submitted_at)?->toIso8601String(),
            'auto_confirmation_due_at' => optional($request->auto_confirmation_due_at)?->toIso8601String(),
            'courier' => $request->courier ? [
                'id' => $request->courier->id,
                'name' => $request->courier->name,
            ] : null,
            'proofs' => $request->proofs->map(fn ($proof) => [
                'type' => $proof->proof_type,
                'url' => $this->mediaUrlResolver->resolve($proof),
                'uploaded_at' => optional($proof->uploaded_at)?->toIso8601String(),
            ])->values()->all(),
            'flags' => [
                'can_confirm' => $isAwaiting,
                'can_dispute' => $isAwaiting,
                'is_auto_confirm_pending' => $isAwaiting && $request->auto_confirmation_due_at?->isFuture(),
            ],
        ];
    }
}
