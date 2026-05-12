# Scheduled Courier Interest / Soft Reservation (PR1 foundation)

## Scope in this PR
- persistence schema `courier_order_interests`;
- Eloquent model `CourierOrderInterest`;
- courier API endpoints to express and withdraw interest;
- basic API contract tests;
- documentation baseline.

## Semantics
- "Готовий виконати" stores courier soft-interest only.
- Soft-interest does **not** hard-assign order.
- Final matching/dispatch logic is intentionally out of scope for this PR.

## Endpoints
- `POST /api/courier/orders/{order}/interest`
- `DELETE /api/courier/orders/{order}/interest`

Both endpoints require authenticated courier.
