# release-20260514-telegram-reconnect-hotfix

Telegram reconnect hotfix release.

## Included changes

- Invalidate active unused bind tokens before issuing a new courier Telegram link.
- Return both Telegram deep link and manual `/start <token>` command in connect flow.
- Persist manual start command in courier profile flash state and render helper UI for manual send.
- Harden webhook parsing for exact `/start <token>` command and add structured ignore logs.
- Invalidate active unused bind tokens on unlink.
- Allow rebind after unlink for the same courier/chat/user with a fresh token while preserving conflict protection.
- Extend feature tests for token lifecycle, reconnect flow, and manual command rendering.

## Deployment notes

Run full backend test suite before deployment.

## Smoke checklist

- Courier can generate Telegram link and sees manual `/start <token>` command.
- Repeated connect keeps only one active unused token.
- Unlink clears Telegram fields and invalidates active unused tokens.
- Old token after unlink is rejected.
- Fresh token after unlink binds courier successfully.
