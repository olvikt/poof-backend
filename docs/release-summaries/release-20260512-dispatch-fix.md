# release-20260512-dispatch-fix

## Summary

Deploys the subscription dispatch scheduler fix and future-deferred searching order monitoring.

## Changes

- Fixes subscription execution order scheduling when the generator runs seconds after the planned slot.
- Prevents every_3_days subscriptions from incorrectly jumping from 2026-05-11 to 2026-05-14.
- Aligns admin searchable/active order visibility with courier dispatch availability.
- Adds diagnostics for courier dispatch visibility by date and courier.
- Adds monitoring for suspicious future-deferred searching orders.

## Incident

Related to the 2026-05-11 courier #2 dispatch incident.

Root cause:
The subscription generator compared timestamps with seconds precision. A job running at 14:00:01 treated the 14:00:00 slot as missed and shifted the next execution by the subscription interval.

## Validation

After deployment run:

php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2
php artisan schedule:list | grep future
