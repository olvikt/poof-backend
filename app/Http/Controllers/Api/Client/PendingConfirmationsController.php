<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Actions\Orders\Completion\GetPendingConfirmationsForClientAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PendingConfirmationsController extends Controller
{
    public function __invoke(GetPendingConfirmationsForClientAction $action): JsonResponse
    {
        $user = auth()->user();
        abort_if(! $user || ! $user->isClient(), 403);

        return response()->json([
            'success' => true,
            'data' => [
                'pending_confirmations' => $action->handle($user),
            ],
        ]);
    }
}
