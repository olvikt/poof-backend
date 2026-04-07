<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Orders\Completion\AutoConfirmOrderCompletionRequestAction;
use App\Models\OrderCompletionRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoConfirmOrderCompletionRequestsCommand extends Command
{
    protected $signature = 'orders:completion-proof:auto-confirm {--limit=100}';

    protected $description = 'Auto-confirm due order completion requests in bounded batches';

    public function handle(AutoConfirmOrderCompletionRequestAction $action): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dueIds = OrderCompletionRequest::query()
            ->where('status', OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION)
            ->whereNotNull('auto_confirmation_due_at')
            ->where('auto_confirmation_due_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        Log::info('completion_auto_confirm_due_found', [
            'flow' => 'order_completion_proof',
            'count' => count($dueIds),
            'batch_limit' => $limit,
        ]);

        $summary = ['confirmed' => 0, 'skipped' => 0, 'missing' => 0];

        foreach ($dueIds as $id) {
            $result = $action->handle((int) $id);
            $summary[$result] = ($summary[$result] ?? 0) + 1;
        }

        $this->line(json_encode($summary, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
