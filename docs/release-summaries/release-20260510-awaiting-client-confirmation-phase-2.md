# Release 2026-05-10: Awaiting Client Confirmation Phase 2

- Client and courier UI now explicitly render awaiting-confirmation messaging, proof context, and auto-confirm countdown/expired-waiting state.
- Completion lifecycle now emits robust notifications (client on proof submit, courier on confirm/auto-complete/dispute) with fail-safe logging.
- Added explicit completion audit table (`order_completion_events`) and event logging for proof submit, confirm, auto-complete, dispute open, and admin resolve.
- Extended completion payload with `server_now`, explicit deadline, and resolution fields for consistent UI countdown behavior.
- Added feature coverage for notification + audit event behavior.

## QA steps
1. Courier uploads proof photos and submits completion.
2. Client sees “Очікує підтвердження” (not “Виконується”), photos, actions, and countdown.
3. Courier sees “Очікує підтвердження клієнта” and countdown.
4. Client confirms -> courier receives notification, settlement stays single.
5. Client disputes -> courier notified, order not auto-completed.
6. After timeout run scheduler -> auto-complete and courier notification.
7. Verify admin/support can inspect dispute queue + completion timeline artifacts.

## Rollback
- Revert this commit and rollback migration `2026_05_10_130000_create_order_completion_events_table`.
- Disable new notification class usages by reverting completion action changes.
