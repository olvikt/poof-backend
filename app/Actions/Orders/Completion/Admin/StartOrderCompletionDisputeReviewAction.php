<?php

declare(strict_types=1);

namespace App\Actions\Orders\Completion\Admin;

use App\Models\OrderCompletionDispute;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StartOrderCompletionDisputeReviewAction
{
    public function handle(OrderCompletionDispute $dispute, User $resolver): bool
    {
        return (bool) DB::transaction(function () use ($dispute, $resolver) {
            $locked = OrderCompletionDispute::query()->whereKey($dispute->id)->lockForUpdate()->first();
            if (! $locked) {
                return false;
            }

            if ($locked->status === OrderCompletionDispute::STATUS_UNDER_REVIEW) {
                return true;
            }

            if ($locked->status !== OrderCompletionDispute::STATUS_OPEN) {
                return false;
            }

            $locked->forceFill([
                'status' => OrderCompletionDispute::STATUS_UNDER_REVIEW,
                'resolver_user_id' => $resolver->id,
            ])->save();

            Log::info('completion_dispute_review_started', [
                'flow' => 'order_completion_proof',
                'order_id' => $locked->order_id,
                'completion_request_id' => $locked->completion_request_id,
                'dispute_id' => $locked->id,
                'resolver_user_id' => $resolver->id,
            ]);

            return true;
        });
    }
}
