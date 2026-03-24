<?php

namespace Tests\Unit\Architecture;

use Tests\TestCase;

class OrderLifecycleActionBoundaryArchitectureTest extends TestCase
{
    private function normalizedFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertIsString($contents);

        return preg_replace('/\s+/', ' ', $contents) ?? '';
    }

    public function test_order_canonical_lifecycle_methods_delegate_to_dedicated_actions_without_local_transactions(): void
    {
        $orderSource = $this->normalizedFile('app/Models/Order.php');

        $this->assertStringContainsString('function markAsPaid(): void { app(MarkOrderAsPaidAction::class)->handle($this); }', $orderSource);
        $this->assertStringContainsString('function acceptBy(User $courier): bool { return app(AcceptOrderByCourierAction::class)->handle($this, $courier); }', $orderSource);
        $this->assertStringContainsString('function startBy(User $courier): bool { return app(StartOrderByCourierAction::class)->handle($this, $courier); }', $orderSource);
        $this->assertStringContainsString('function completeBy(User $courier): bool { return app(CompleteOrderByCourierAction::class)->handle($this, $courier); }', $orderSource);
        $this->assertStringContainsString('function cancel(): bool { return app(CancelOrderAction::class)->handle($this); }', $orderSource);

        $this->assertStringNotContainsString('DB::transaction', $orderSource);
        $this->assertStringNotContainsString('lockForUpdate', $orderSource);
    }

    public function test_legacy_entry_points_remain_backward_compatible_through_canonical_by_methods(): void
    {
        $orderSource = $this->normalizedFile('app/Models/Order.php');

        $this->assertStringContainsString('function start(): bool { $courier = $this->courier; if (! $courier instanceof User) { return false; } return $this->startBy($courier); }', $orderSource);
        $this->assertStringContainsString('function complete(): bool { $courier = $this->courier; if (! $courier instanceof User) { return false; } return $this->completeBy($courier); }', $orderSource);
    }

    public function test_transactional_orchestration_stays_inside_lifecycle_actions(): void
    {
        $transactionalActions = [
            'app/Actions/Orders/Lifecycle/AcceptOrderByCourierAction.php',
            'app/Actions/Orders/Lifecycle/StartOrderByCourierAction.php',
            'app/Actions/Orders/Lifecycle/CompleteOrderByCourierAction.php',
            'app/Actions/Orders/Lifecycle/CancelOrderAction.php',
        ];

        foreach ($transactionalActions as $actionFile) {
            $source = $this->normalizedFile($actionFile);
            $this->assertStringContainsString('DB::transaction(function', $source, "Expected transaction boundary in {$actionFile}");
            $this->assertStringContainsString('->lockForUpdate()', $source, "Expected row lock in {$actionFile}");
        }
    }

    public function test_accept_action_keeps_courier_lock_before_order_lock(): void
    {
        $source = $this->normalizedFile('app/Actions/Orders/Lifecycle/AcceptOrderByCourierAction.php');

        $courierLockPosition = strpos($source, 'User::query() ->whereKey($courier->getKey()) ->lockForUpdate()');
        $orderLockPosition = strpos($source, 'Order::query() ->whereKey($order->getKey()) ->lockForUpdate()');

        $this->assertNotFalse($courierLockPosition);
        $this->assertNotFalse($orderLockPosition);
        $this->assertLessThan($orderLockPosition, $courierLockPosition);
    }
}
