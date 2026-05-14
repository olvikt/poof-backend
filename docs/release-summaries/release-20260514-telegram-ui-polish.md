# release-20260514-telegram-ui-polish

## Summary
Telegram reconnect UX polish for courier profile.

## Changes
- Reworked Telegram reconnect UI hierarchy.
- Primary CTA opens Telegram app via native link.
- Secondary CTA opens Telegram browser/web fallback.
- Added one-tap copy button for manual /start command.
- Added e2e hooks for native open, browser open, start command, and copy command.
- Added feature coverage for conditional rendering and copy command UI.

## Verification
- Courier profile renders Telegram reconnect block.
- Native Telegram CTA is shown only when native link exists.
- Browser Telegram CTA is shown only when HTTPS link exists.
- Manual /start command block is shown only when command exists.
- Copy button copies the full /start command.

## Risk
Low: Blade-only UX change plus feature tests.

## Rollback
Rollback to release-20260514-telegram-deeplink-fix if Telegram profile UI regresses.
