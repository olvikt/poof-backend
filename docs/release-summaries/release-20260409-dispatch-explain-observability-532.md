# Release 2026-04-09 — Dispatch DB/perf hardening evidence pack (#532)

## What changed

- Added production-style EXPLAIN pack for Q1–Q6 dispatch/runtime hot queries with exact SQL templates and “good plan” expectations.
- Normalized dispatch candidate observability contract across outcomes:
  - `candidate_scan_count`
  - `candidate_count`
  - `search_radius_km`
  - `bbox_prefilter_applied`
  - `trigger_source`
  - `elapsed_ms`
- Added dedicated marker `dispatch_candidates_evaluated` for bounded candidate fetch telemetry.
- Expanded dispatch outcome markers (`dispatch_no_candidates`, `dispatch_no_pick`, `dispatch_offer_created`) with consistent candidate/latency dimensions.
- Updated release checklist to include EXPLAIN runbook reference, watch-window metrics, and rollback notes for new dispatch observability fields.
- Added architecture regression guard to keep hot query boundaries stable (candidate SQL projection + available/my-orders contracts).

## Query shape changes

- No canonical runtime truth model changes.
- No scoring architecture changes (SQL prefilter + PHP scoring remains unchanged).
- Candidate SQL semantics unchanged (same eligibility filters and correlated busy-order guard).
- Only observability payloads were expanded.

## Metrics to watch

- `dispatch_candidates_evaluated` candidate cardinality (`candidate_scan_count`, `candidate_count`) and latency (`elapsed_ms`) by `trigger_source`.
- Dispatch outcome p50/p95/p99 from `dispatch_started` to:
  - `dispatch_offer_created`
  - `dispatch_no_candidates`
  - `dispatch_no_pick`
  - `dispatch_deferred`.

## Optimization status

- Bounded optimization implemented: explicit candidate fetch telemetry marker.
- Candidate selection algorithm redesign deferred pending production EXPLAIN and telemetry evidence.

## Cost impact

- DB: no new heavy subqueries, no schema changes.
- CPU: unchanged dispatch scoring semantics.
- Logging: one additional structured debug marker per dispatch attempt + enriched outcome payload fields.

