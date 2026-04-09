# MyOrders hot/cold read-path split evidence

## Before split (`MyOrders::render` monolith)

Every poll on `MyOrders` executed all of the following in one render loop:

1. Active orders read (`orders` for `accepted`/`in_progress`).
2. Completed stats read (`courier_earnings` + `orders` aggregation).
3. Nearby area summary aggregation (`order_offers` + `orders` grouped subquery).
4. Runtime map bootstrap + distance decoration.

This meant active polling sessions were paying completed stats aggregation cost on each render cycle.

## After split

### Hot runtime pane (`activeTab=orders`)

Runs:

1. Active orders read (`orders` for active statuses + relations).
2. Runtime map bootstrap + distance decoration.
3. Nearby summary from bounded TTL cache key `courier:{id}:my-orders:nearby-area-summary` (aggregate query only on cache miss).

Does **not** run:

1. Completed stats read (`courier_earnings` aggregate path).

### Cold stats pane (`activeTab=stats`)

Runs:

1. Completed stats read via `CourierCompletedOrdersDailyStatsQuery`.
2. Existing accordion/date expansion state reconciliation.

Failure mode:

1. Stats failure is isolated (`my_orders_stats_render_failed` warning + `statsPaneUnavailable=true` UI notice).
2. Active orders pane remains operational and canonical runtime actions are unaffected.

## Query evidence tests

`tests/Feature/Courier/CourierMyOrdersHotColdReadPathTest.php` adds assertions that:

1. Orders tab render does not execute `courier_earnings` queries.
2. Stats tab render executes `courier_earnings` queries after tab switch.
3. Stats query failure does not break active orders pane correctness.
