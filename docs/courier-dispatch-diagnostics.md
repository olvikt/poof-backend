# Courier dispatch diagnostics by date

Команда для прод-диагностики причин, почему заказ не виден конкретному курьеру в `available-orders`.

## Запуск

```bash
php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2
```

Команда выводит JSON с массивом `orders` за дату `scheduled_date`.

## Что проверяется по каждому заказу

- поля заказа: `id`, `order_type`, `origin`, `subscription_id`, `status`, `payment_status`, `courier_id`;
- тайминги dispatch: `dispatch_available_at`, `next_dispatch_at`, `valid_until_at`, `expired_at`;
- флаги: `is_dispatch_deferred`, `is_promise_expired`, `is_dispatchable_for_offer_pipeline`;
- офферы: `alive_pending_offers_count`, `has_offer_for_courier_id`, `has_alive_pending_offer_for_courier_id`;
- `dispatch_window_opens_at` (если `dispatch_available_at` в будущем);
- machine-readable `reasons`.

## Расшифровка reasons

- `status_not_searching` — заказ не в `searching`, OfferDispatcher не крутит его.
- `payment_not_paid` — заказ не `paid`, pipeline офферов блокируется.
- `courier_already_assigned` — у заказа уже есть `courier_id`.
- `next_dispatch_backoff_until_future` — backoff активен, `next_dispatch_at` ещё в будущем.
- `dispatch_available_at_in_future` — окно dispatch ещё не открылось.
- `order_promise_expired` — promise уже истёк (или заказ auto-expired).
- `waiting_alive_pending_offer` — есть живой pending offer, новый оффер не создаётся.
- `bug_needs_dispatch_no_alive_offer` — заказ dispatchable, но живого оффера нет; это сигнал для ручного запуска dispatch loop/инцидентного расследования.

## Как интерпретировать для кейса “курьер 2 не видит подписки”

1. Фильтруем `orders` по `origin=subscription` или `subscription_id != null`.
2. Проверяем `reasons`:
   - если есть `dispatch_available_at_in_future`, ждать `dispatch_window_opens_at`;
   - если есть `next_dispatch_backoff_until_future`, ждать `next_dispatch_at`;
   - если есть `waiting_alive_pending_offer` и `has_alive_pending_offer_for_courier_id=false`, оффер сейчас у другого курьера;
   - если только `bug_needs_dispatch_no_alive_offer`, это аномалия.
3. Для аномалий запускаем `courier:diagnose-searching-orders` и `courier:why-order-not-dispatched {orderId}`.
