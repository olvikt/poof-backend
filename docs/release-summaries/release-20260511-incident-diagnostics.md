# Release 2026-05-11 — Courier dispatch incident diagnostics hardening

- Extended `poof:diagnose-courier-dispatch` output with:
  - canonical reason codes (`dispatch_deferred_future_window`, `not_dispatchable_status`, `not_paid`, `courier_already_assigned`, `no_alive_pending_offer`, `no_offer_for_courier`);
  - aggregated summary counters (`dispatchable`, `deferred`, `stuck_without_offers`);
  - queue snapshot (`failed_jobs`, `delayed_jobs`, `dispatcher_jobs`);
  - timezone snapshot (`app_timezone`, `php_timezone`, `db_now`, `carbon_now`).
- Updated diagnostics docs to match new reason taxonomy and report format.
- Added regression assertions for summary and diagnostics shape in command feature tests.
