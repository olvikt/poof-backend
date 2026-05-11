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

- `accepted -> cancelled` is forbidden in default flow and allowed only via explicit `admin_override` flow.
- `new -> done` is forbidden in default flow and allowed only via explicit `subscription_checkout` flow.

Forbidden transitions:

- `done|cancelled|expired -> *`
- `new -> accepted`
- `new -> in_progress`
- `searching -> done`

Implementation:

- Central policy: `App\Support\Orders\OrderLifecycleTransitionPolicy`.
- All lifecycle write entrypoints must call `assertTransition()` before writing `orders.status`.
- Generic cancel flow (`CancelOrderAction`) allows only `new/searching -> cancelled`.
- Admin override cancel flow (`CancelOrderAction::handleAdminOverride`) is for admin/system entrypoints and supports `accepted -> cancelled`.
- In-progress cancellation is not allowed by policy at this stage.
- Subscription checkout payment flow uses explicit `subscription_checkout` policy flow for `new -> done`.
- Auto-expire flow writes `searching -> expired`.
