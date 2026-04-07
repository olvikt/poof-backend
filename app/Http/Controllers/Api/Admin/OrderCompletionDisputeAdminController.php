<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Orders\Completion\Admin\ResolveOrderCompletionDisputeAction;
use App\Actions\Orders\Completion\Admin\StartOrderCompletionDisputeReviewAction;
use App\Http\Controllers\Controller;
use App\Models\OrderCompletionDispute;
use App\Services\Orders\Completion\OrderCompletionProofMediaUrlResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderCompletionDisputeAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $user = auth()->user();
        abort_if(! $user || ! $user->isAdmin(), 403);

        $queue = OrderCompletionDispute::query()
            ->with(['order', 'client', 'courier'])
            ->whereIn('status', [OrderCompletionDispute::STATUS_OPEN, OrderCompletionDispute::STATUS_UNDER_REVIEW])
            ->orderBy('opened_at')
            ->limit(100)
            ->get()
            ->map(fn (OrderCompletionDispute $item) => [
                'id' => $item->id,
                'order_id' => $item->order_id,
                'status' => $item->status,
                'opened_at' => optional($item->opened_at)?->toIso8601String(),
                'days_open' => $item->opened_at ? $item->opened_at->diffInDays(now()) : null,
                'reason_code' => $item->reason_code,
                'comment' => $item->comment,
                'client' => ['id' => $item->client?->id, 'name' => $item->client?->name],
                'courier' => ['id' => $item->courier?->id, 'name' => $item->courier?->name],
            ])->values();

        return response()->json(['success' => true, 'data' => $queue]);
    }

    public function show(OrderCompletionDispute $dispute, OrderCompletionProofMediaUrlResolver $resolver): JsonResponse
    {
        $user = auth()->user();
        abort_if(! $user || ! $user->isAdmin(), 403);

        $dispute->load(['request.proofs', 'order', 'client', 'courier']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $dispute->id,
                'order_id' => $dispute->order_id,
                'status' => $dispute->status,
                'reason_code' => $dispute->reason_code,
                'comment' => $dispute->comment,
                'opened_at' => optional($dispute->opened_at)?->toIso8601String(),
                'request_status' => $dispute->request?->status,
                'proofs' => $dispute->request?->proofs->map(fn ($proof) => [
                    'type' => $proof->proof_type,
                    'url' => $resolver->resolve($proof),
                ])->values()->all() ?? [],
            ],
        ]);
    }

    public function markUnderReview(OrderCompletionDispute $dispute, StartOrderCompletionDisputeReviewAction $action): JsonResponse
    {
        $user = auth()->user();
        abort_if(! $user || ! $user->isAdmin(), 403);

        if (! $action->handle($dispute, $user)) {
            return response()->json(['success' => false], 409);
        }

        return response()->json(['success' => true]);
    }

    public function resolveConfirmed(OrderCompletionDispute $dispute, Request $request, ResolveOrderCompletionDisputeAction $action): JsonResponse
    {
        return $this->resolve($dispute, $request, $action, true);
    }

    public function resolveRejected(OrderCompletionDispute $dispute, Request $request, ResolveOrderCompletionDisputeAction $action): JsonResponse
    {
        return $this->resolve($dispute, $request, $action, false);
    }

    private function resolve(OrderCompletionDispute $dispute, Request $request, ResolveOrderCompletionDisputeAction $action, bool $approve): JsonResponse
    {
        $user = auth()->user();
        abort_if(! $user || ! $user->isAdmin(), 403);

        $validated = $request->validate(['resolution_note' => ['nullable', 'string', 'max:2000']]);

        if (! $action->handle($dispute, $user, $approve, $validated['resolution_note'] ?? null)) {
            return response()->json(['success' => false], 409);
        }

        return response()->json(['success' => true]);
    }
}
