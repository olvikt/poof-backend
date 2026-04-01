# WayForPay integration handoff (production-ready scaffold)

## Целевая доменная схема

- `poof.com.ua` — маркетинговый сайт.
- `app.poof.com.ua` — клієнтський застосунок.
- `api.poof.com.ua` — backend API, callback-и, webhook-и.

## Env-переменные

`.env.example` в репозитории intentionally local-safe (для dev/CI). Ниже — значения именно для production окружения.

```dotenv
APP_URL=https://app.poof.com.ua
ASSET_URL=https://app.poof.com.ua
VITE_API_URL=https://api.poof.com.ua
SESSION_DOMAIN=.poof.com.ua
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=app.poof.com.ua

PAYMENTS_PROVIDER=wayforpay
PAYMENTS_DEV_FALLBACK_ENABLED=false

WAYFORPAY_ENABLED=true
WAYFORPAY_MERCHANT_ACCOUNT=
WAYFORPAY_MERCHANT_SECRET=
WAYFORPAY_MERCHANT_DOMAIN=app.poof.com.ua
WAYFORPAY_SERVICE_URL=https://api.poof.com.ua/api/payments/wayforpay/callback
WAYFORPAY_RETURN_URL=https://app.poof.com.ua/payments/wayforpay/return
WAYFORPAY_APPROVED_URL=https://app.poof.com.ua/client/orders
WAYFORPAY_DECLINED_URL=https://app.poof.com.ua/client/orders
WAYFORPAY_CURRENCY=UAH
WAYFORPAY_LANGUAGE=UA
WAYFORPAY_PAY_URL=https://secure.wayforpay.com/pay
```

> Не храните merchant secret в репозитории. Вносится только в production `.env`.

## Роуты

- Клиентская страница оплаты: `GET /client/payments/{order}`.
- Инициация оплаты: `POST /client/payments/{order}/start`.
- Dev fallback (ограниченный): `POST /client/payments/dev-pay/{order}`.
- WayForPay callback (источник истины по статусу): `POST /api/payments/wayforpay/callback`.
- WayForPay return (только UX-возврат пользователя): `GET|POST /payments/wayforpay/return`.

## Что указать в кабинете WayForPay

- `merchantAccount`: `poof_com_ua`
- `merchantDomainName`: `app.poof.com.ua`
- `serviceUrl`: `https://api.poof.com.ua/api/payments/wayforpay/callback`
- `returnUrl`: `https://app.poof.com.ua/payments/wayforpay/return`

> `approvedUrl`/`declinedUrl` из нашего `.env` используются внутри приложения на этапе редиректа с `return` endpoint и не должны указывать на прямой POST-target вроде `/client/orders`.

## Callback vs return (разделение ответственности)

- **Callback** (`POST /api/payments/wayforpay/callback`) — единственный источник истины для payment status.
  - Endpoint работает в API-style и **не делает redirect** на ошибках валидации/обработки.
  - Поддерживаются payload форматы:
    - `application/json` (обычный JSON body),
    - `application/x-www-form-urlencoded` (обычный form payload),
    - нестандартный кейс: JSON строкой в одном form-urlencoded key/value.
  - Подпись callback проверяется по `merchantSecret`.
  - `orderReference` сопоставляется с `orders.id`.
  - Для статуса `Approved` заказ переводится в `paid` через доменное действие `markAsPaid()`.
  - Обработка идемпотентна: повторный `Approved` callback на уже оплаченный заказ безопасно игнорируется.
  - Для неуспешных/ожидающих статусов (`Refunded`, `Declined`, и т.д.) заказ не помечается оплаченным.
  - Коды ответов callback:
    - `200` — callback принят/обработан успешно (`status=accept`).
    - `422` — невалидный payload или невалидная подпись.
    - `404` — заказ по `orderReference` не найден.
- **Return** (`GET|POST /payments/wayforpay/return`) — только пользовательский возврат в UI.
  - Не валидирует callback-подпись.
  - Не меняет `payment_status` заказа.
  - Сначала делает технический переход на same-site endpoint `GET /payments/wayforpay/return/finalize`, и только там проверяет auth/session.
  - Это устраняет production-кейс, когда cross-site POST return не присылает `laravel_session` (типичное поведение при `SameSite=Lax`) и преждевременно отправляет пользователя на `/login`.
  - При успешном восстановлении сессии на finalize-step пользователь сразу уходит на `WAYFORPAY_APPROVED_URL`/`WAYFORPAY_DECLINED_URL` без повторного логина.
  - Если сессия реально утрачена даже после same-site re-entry, flow уходит на `/login` с `next`-параметром и fallback-cookie для автоматического возврата.

## Диагностика callback в production

Смотрите `storage/logs/laravel.log` на события:

- `WayForPay callback received.` — входящий callback (логирует source IP, path, content-type, ключи payload).
- `WayForPay callback rejected: invalid payload.` — проблемы структуры payload + список отсутствующих required полей.
- `WayForPay callback rejected: invalid signature.` — signature verify не прошёл.
- `WayForPay callback rejected: order not found.` — `orderReference` не найден в `orders`.
- `WayForPay transaction status received.` — лог входящего `transactionStatus` с `order_id`/`order_reference`.
- `WayForPay callback received non-success transaction status.` — диагностический warning для `Refunded`, `Declined` и других non-success статусов.
- `WayForPay duplicate callback ignored: order already paid.` — повторный `Approved` callback на уже оплаченный заказ.
- `WayForPay callback processed successfully.` — success path, callback обработан.
- `WayForPay return endpoint visited.` — вход на return endpoint (method/host/path/query_keys + флаги наличия session/XSRF cookies + выбранный redirect).
- `WayForPay return finalize resolved with active session.` — после same-site re-entry сессия валидна, выполнен прямой redirect в orders flow.
- `WayForPay return finalize handled without active session; redirecting to login.` — даже после same-site re-entry user остался guest, включён fallback через login.

Практический triage:

1. Если в nginx access log есть `POST /api/payments/wayforpay/callback`, а в приложении нет `...received`, проверьте upstream/PHP-FPM path и фактический vhost.
2. Если есть `invalid payload`, сравните фактический content-type и поля callback с требованиями endpoint.
3. Если `invalid signature`, перепроверьте `WAYFORPAY_MERCHANT_SECRET` и порядок полей при подписании.
4. Если callback падает, но пользователь вернулся в UI — это normal: `return` поток не подтверждает оплату и не должен использоваться как источник истины.

## Production checklist (боевой прогон)

1. Проверить в кабинете WayForPay, что merchant **не в test/tested режиме**.
2. Проверить, что тестовые/проверочные платежи **не auto-refund-ятся**.
3. Убедиться, что используется production merchant profile: `poof_com_ua`.
4. Повторять боевой платеж только после подтверждения live-режима и актуальных merchant credentials.
5. После оплаты проверить:
   - что заказ перешел в `paid` только после callback,
   - что в логах есть `WayForPay callback received.` и `WayForPay callback processed successfully.`,
   - что в логах return-потока есть `...return_visited` + `...return_finalize_authenticated` для штатного сценария,
   - что return flow возвращает пользователя в UX маршрут (orders/result) и не меняет payment status сам по себе.

## Manual server steps (выполняются вне репозитория)

1. Создать `/etc/nginx/sites-available/poof-app` по шаблону `docs/deployment/nginx-app.poof.com.ua.conf.example`.
2. Сделать symlink в `sites-enabled`, проверить `nginx -t`, затем reload nginx.
3. После рабочего HTTP vhost выпустить HTTPS для `app.poof.com.ua` через certbot.
4. Проставить production `.env` значения для доменов/Sanctum/session/WayForPay.
5. После деплоя выполнить cache rebuild и reload/restart PHP-FPM/Nginx/workers по стандартному release runbook.
6. В кабинете WayForPay заполнить production URL и merchant credentials.
7. Рекомендуется отключить любые legacy-настройки, которые принудительно POST-ят пользователя напрямую на `/client/orders`; return должен идти только на `/payments/wayforpay/return`.

## Важно про assets/API origin

- `VITE_API_URL` — это endpoint для API calls из UI.
- Vite build assets должны раздаваться same-origin с `app.poof.com.ua` (через `APP_URL`/`ASSET_URL`).
- Не направляйте frontend assets на `api.poof.com.ua`: это ломает загрузку JS/CSS в браузере и может привести к CORS/runtime ошибкам.
