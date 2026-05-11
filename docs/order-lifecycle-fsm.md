# Order Lifecycle FSM Contract

Canonical transitions (`default` flow):

- `new -> searching`
- `searching -> accepted`
- `accepted -> in_progress`
- `in_progress -> done`
- `new -> cancelled`
- `searching -> cancelled`
- `searching -> expired`

Restricted transition:

- `accepted|in_progress -> cancelled` is forbidden in default flow and allowed only via explicit `admin_override` flow.

Forbidden transitions:

- `done|cancelled|expired -> *`
- `new -> accepted`
- `new -> in_progress`
- `searching -> done`

Implementation:

- Central policy: `App\Support\Orders\OrderLifecycleTransitionPolicy`.
- All lifecycle write entrypoints must call `assertTransition()` before writing `orders.status`.
- Generic cancel flow (`CancelOrderAction`) allows only `new/searching -> cancelled`.
- Auto-expire flow writes `searching -> expired`.
