<?php

declare(strict_types=1);

namespace App\Services\Dispatch;

final class DispatchDiagnosticReason
{
    public const DISPATCH_DEFERRED_UNTIL = 'dispatch_deferred_until';
    public const WAITING_LIVE_OFFER = 'waiting_live_offer';
    public const NO_CANDIDATES = 'no_candidates';
    public const NO_PICK = 'no_pick';
    public const ORDER_PROMISE_EXPIRED = 'order_promise_expired';
    public const MISSING_COORDINATES = 'missing_coordinates';
    public const COURIER_BUSY = 'courier_busy';
    public const COURIER_OFFLINE_OR_STALE = 'courier_offline_or_stale';
    public const PAYMENT_NOT_PAID = 'payment_not_paid';
    public const INVALID_STATUS = 'invalid_status';
}

