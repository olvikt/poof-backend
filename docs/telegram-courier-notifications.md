# Telegram courier notifications

## Bind flow
1. Courier opens profile and requests Telegram link.
2. Backend creates one-time token (`telegram_bind_tokens`) with TTL (`TELEGRAM_BIND_TOKEN_TTL_MINUTES`).
3. Courier opens deep link `https://t.me/<bot>?start=<token>`.
4. Telegram bot webhook sends `/start <token>` update to `/api/telegram/webhook`.
5. Backend validates token (exists, not expired, not used), binds `telegram_chat_id`, `telegram_user_id`, `telegram_username`, sets `telegram_linked_at`, and marks token `used_at`.
6. Audit events: `courier_telegram_bound`, `courier_telegram_unlinked`.

## Localization strategy
- Courier-facing Telegram UX strings are centralized in `lang/uk/courier.php`.
- Views and services must resolve strings via localization keys (no RU/EN hardcoded UI copy).
- Telegram notification text templates are rendered in Ukrainian and sourced from `courier.notifications.*`.
- Legacy technical templates like `[scheduled_*] Order #... update` are forbidden.

## Notification templates (Ukrainian)
- `scheduled_final_offer`
  - `🚚 Нове замовлення`
  - pickup/delivery/window/amount with emoji and multiline formatting
  - TTL text: `⏳ У вас є :ttl секунд, щоб прийняти замовлення.`
- `scheduled_offer_expiring_soon`
  - `⚠️ Час майже вичерпано`
- `scheduled_reservation_lost`
  - `ℹ️ Замовлення вже передано іншому курʼєру.`
- `scheduled_order_visible`
  - `📦 Доступне нове заплановане замовлення`

Formatting rules:
- Money format: integer amount + `₴` (e.g. `129 ₴`).
- Time window: `HH:MM–HH:MM`, fallback to helper text when not available.
- Address fallback: helper text when missing.

## Preferences
- `telegram_notifications_orders_enabled` (default `true`): order lifecycle notifications.
- `telegram_notifications_marketing_enabled` (default `false`): marketing/news messages.
- `push_notifications_orders_enabled` still respected for operational order notifications.

## Courier profile UI block
- Location: `resources/views/courier/profile.blade.php` (`GET /courier/profile`).
- Title: `Telegram-сповіщення`.
- Status:
  - `Не підʼєднано` when `profile.telegram.telegram_linked=false`;
  - `Підʼєднано: @username` when linked.
- Actions:
  - `Підʼєднати Telegram` -> `POST /courier/profile/telegram/link`;
  - `Відкрити Telegram` from session `telegram_deep_link`;
  - `Відʼєднати Telegram` -> `POST /courier/profile/telegram/unlink`.
- If bot is not configured, profile shows:
  - `Telegram бот тимчасово недоступний.`

## Env/config
- Canonical config key for runtime token reads: `config('services.telegram.bot_token')` (single source of truth).
- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_BOT_USERNAME`
- `TELEGRAM_BIND_TOKEN_TTL_MINUTES` (default `15`)

### Troubleshooting Telegram 404
- Symptom: Telegram send endpoints fail with `404 Not Found` while webhook/binding may still work.
- Verify canonical config read in runtime code: `config('services.telegram.bot_token')`.
- Do **not** use legacy/non-canonical keys like `config('telegram.bot_token')`.
- Check `telegram_error` logs for:
  - sanitized endpoint (`https://api.telegram.org/bot<redacted>/sendMessage`),
  - Telegram API `description`,
  - `response_code`.
- If token is missing, send is skipped/fails in controlled way and no HTTP request is built with an empty token.

## Security constraints
- Token is hashed in DB (`sha256`).
- Token TTL enforced.
- Token is one-time (re-use rejected).
- Invalid/expired/reused tokens are rejected with `422`.

## Admin send flow (Filament)
- Location: `Адмінка -> Courier -> Couriers` (`/admin/couriers`).
- Added courier table Telegram columns:
  - linked status (yes/no),
  - `telegram_username`,
  - `telegram_linked_at`,
  - orders preference,
  - marketing preference.
- Added filters:
  - `Telegram прив’язано`,
  - `Telegram не прив’язано`,
  - `Увімкнено сповіщення про замовлення`,
  - `Увімкнено новини та акції`.
- Added actions:
  - single row action `Надіслати Telegram сповіщення`,
  - bulk action `Надіслати Telegram сповіщення` with confirmation.

### Admin notification policy
- `news_marketing`: sent only to linked couriers with `telegram_notifications_marketing_enabled=true`.
- `order_service`: sent only to linked couriers.
- `order_service` + `is_emergency=false`: respects `telegram_notifications_orders_enabled=true`.
- `order_service` + `is_emergency=true`: allows critical service/emergency bypass.

### Delivery result and audit
- Admin result toast includes:
  - sent count,
  - skipped not linked,
  - skipped by preferences,
  - failed count.
- Every recipient attempt is persisted into `telegram_admin_notifications`:
  - `admin_id`, `courier_id`, `notification_type`,
  - `status` (`sent|skipped|failed`),
  - `telegram_error`,
  - `title`, `message`, `is_emergency`, timestamps.

## Production smoke checklist
1. Admin can open `/admin/couriers` and see Telegram columns.
2. Filters for linked/unlinked and preferences return expected subsets.
3. Single send to linked courier with enabled preference creates `sent` audit row.
4. Send to unlinked courier creates `skipped/not_linked` audit row.
5. Marketing send to disabled courier creates `skipped/marketing_disabled` audit row.
6. Bulk send shows aggregated counters in admin result toast.
7. Non-admin user cannot access `/admin/couriers`.
8. Message validation blocks empty text and enforces max length.
