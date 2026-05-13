# Release summary: Courier Telegram ukrainization

## Included
- Full ukrainization of courier Telegram profile block labels/status/actions/preferences.
- Human-readable Ukrainian Telegram order notifications (no legacy `[scheduled_*]` templates).
- Added centralized localization keys for courier Telegram UX and notification templates.
- Added tests for notification body rendering and legacy-template absence.

## QA focus
- Profile Telegram block strings are Ukrainian-only.
- Final offer notification includes multiline body, amount, time window, and TTL line.
- Expiring/lost messages use Ukrainian human-readable templates.
