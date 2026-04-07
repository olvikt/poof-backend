<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Actions\Orders\Completion\ConfirmOrderCompletionByClientAction;
use App\Actions\Orders\Completion\CreateOrderCompletionDisputeAction;
use App\Actions\Orders\Completion\GetOrderCompletionClientPayloadAction;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderCompletionClientController extends Controller
{
    public function show(Order $order, GetOrderCompletionClientPayloadAction $action): JsonResponse
    {
        $user = auth()->user();
        abort_if(! $user || ! $user->isClient(), 403);

        $payload = $action->handle($order, $user);
        if (! $payload) {
            return response()->json(['success' => false], 404);
        }

        return response()->json(['success' => true, 'data' => $payload]);
    }

    public function confirm(Order $order, ConfirmOrderCompletionByClientAction $action): JsonResponse
    {
        $user = auth()->user();
        abort_if(! $user || ! $user->isClient(), 403);

        if (! $action->handle($order, $user)) {
            return response()->json(['success' => false], 409);
        }

        return response()->json(['success' => true]);
    }

    public function openDispute(Order $order, Request $request, CreateOrderCompletionDisputeAction $action): JsonResponse
    {
        $user = auth()->user();
        abort_if(! $user || ! $user->isClient(), 403);

        $validated = $request->validate([
            'reason_code' => ['required', 'string', 'max:64'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $action->handle($order, $user, $validated['reason_code'], $validated['comment'] ?? null)) {
            return response()->json(['success' => false], 409);
        }

        return response()->json(['success' => true]);
    }
}
