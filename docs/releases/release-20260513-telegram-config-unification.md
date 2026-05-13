# Release 2026-05-13: Telegram config key unification and 404 diagnostics

- Unified Telegram Bot token reads to canonical key: `config('services.telegram.bot_token')`.
- Hardened admin and scheduled Telegram sends when token is missing (no outbound call with empty token).
- Added structured `telegram_error` warning logs with sanitized endpoint, Telegram description, and response code.
- Added regression tests for token usage and missing-token behavior.
