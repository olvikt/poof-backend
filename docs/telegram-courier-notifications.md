# Telegram courier notifications

## Bind flow
1. Courier opens profile and requests Telegram link.
2. Backend creates one-time token (`telegram_bind_tokens`) with TTL (`TELEGRAM_BIND_TOKEN_TTL_MINUTES`).
3. Courier opens deep link `https://t.me/<bot>?start=<token>`.
4. Telegram bot webhook sends `/start <token>` update to `/api/telegram/webhook`.
5. Backend validates token (exists, not expired, not used), binds `telegram_chat_id`, `telegram_user_id`, `telegram_username`, sets `telegram_linked_at`, and marks token `used_at`.
6. Audit events: `courier_telegram_bound`, `courier_telegram_unlinked`.

## Preferences
- `telegram_notifications_orders_enabled` (default `true`): order lifecycle notifications.
- `telegram_notifications_marketing_enabled` (default `false`): marketing/news messages.
- `push_notifications_orders_enabled` still respected for operational order notifications.

## Courier profile UI block
- Location: `resources/views/courier/profile.blade.php` in courier cabinet page (`GET /courier/profile`).
- Block title: `Telegram уведомления`.
- Status rendering:
  - `Не привязан` when `profile.telegram.telegram_linked=false`;
  - `Привязан: @username` when linked.
- Actions:
  - `Привязать Telegram` -> `POST /courier/profile/telegram/link`;
  - `Открыть Telegram` appears when backend returns `telegram_deep_link` in session;
  - `Отвязать Telegram` -> `POST /courier/profile/telegram/unlink`.
- Preferences form:
  - `Уведомления о заказах`;
  - `Новости и акции`;
  - persisted via `POST /courier/profile/telegram/preferences`.
- If `TELEGRAM_BOT_USERNAME` is missing, profile shows explicit unavailability message and hides bind CTA.

## Production smoke
- Courier profile shows Telegram binding block on `/courier/profile`.
- Unlinked courier sees `Привязать Telegram`.
- Linked courier sees `Привязан: @username` and preferences toggles.

## Env/config
- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_BOT_USERNAME`
- `TELEGRAM_BIND_TOKEN_TTL_MINUTES` (default `15`)

## Security constraints
- Token is hashed in DB (`sha256`).
- Token TTL enforced.
- Token is one-time (re-use rejected).
- Invalid/expired/reused tokens are rejected with `422`.
