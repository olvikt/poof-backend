# WayForPay integration handoff (production-ready scaffold)

## Целевая доменная схема

- `poof.com.ua` — маркетинговый сайт.
- `app.poof.com.ua` — клієнтський застосунок.
- `api.poof.com.ua` — backend API, callback-и, webhook-и.

## Env-переменные

`.env.example` в репозитории intentionally local-safe (для dev/CI). Ниже — значения именно для production окружения.

```dotenv
PAYMENTS_PROVIDER=wayforpay
PAYMENTS_DEV_FALLBACK_ENABLED=false

WAYFORPAY_ENABLED=true
WAYFORPAY_MERCHANT_ACCOUNT=
WAYFORPAY_MERCHANT_SECRET=
WAYFORPAY_MERCHANT_DOMAIN=app.poof.com.ua
WAYFORPAY_SERVICE_URL=https://api.poof.com.ua/api/payments/wayforpay/callback
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
- WayForPay callback: `POST /api/payments/wayforpay/callback`.

## Что указать в кабинете WayForPay

- `merchantAccount`: `poof_com_ua`
- `merchantDomainName`: `app.poof.com.ua`
- `serviceUrl`: `https://api.poof.com.ua/api/payments/wayforpay/callback`
- `approvedUrl`: `https://app.poof.com.ua/client/orders`
- `declinedUrl`: `https://app.poof.com.ua/client/orders`

## Callback behavior

- Подпись callback проверяется по `merchantSecret`.
- `orderReference` сопоставляется с `orders.id`.
- Для статуса `Approved` заказ переводится в `paid` через доменное действие `markAsPaid()`.
- Обработка идемпотентна: повторный callback на уже оплаченный заказ не ломает состояние.
- Для неуспешных/ожидающих статусов заказ не помечается оплаченным.

## Manual server steps (выполняются вне репозитория)

1. Создать Nginx vhost для `app.poof.com.ua` (frontend/web routes).
2. Выпустить/подключить SSL сертификат для `app.poof.com.ua`.
3. Проставить production `.env` значения для доменов/Sanctum/session/WayForPay.
4. После деплоя выполнить cache rebuild и reload/restart PHP-FPM/Nginx/workers по стандартному release runbook.
5. В кабинете WayForPay заполнить production URL и merchant credentials.
