# Release 2026-05-13: Admin Telegram courier notifications

- Added Telegram status visibility for couriers in admin table.
- Added admin filters for Telegram linked state and Telegram notification preferences.
- Added single and bulk admin actions to send Telegram notifications to couriers.
- Added notification type policy handling with emergency override for service notices.
- Added delivery audit table `telegram_admin_notifications` with per-courier send/skip/fail entries.
- Added automated coverage for admin visibility, access control, and send policy/audit behavior.
