# ADR: Courier verification flow (Phase 1)

## Status
Accepted — 2026-04-08.

## Context
`users.is_verified` and `couriers.is_verified` existed as compatibility flags, but they were insufficient as workflow source-of-truth.

Courier profile needs a real document submission and admin review lifecycle without polluting hot profile fields.

## Decision
Verification is isolated into bounded context table `courier_verification_requests`.

Canonical verification lifecycle:
- `pending_review`
- `verified`
- `rejected`

User-facing projection on courier profile:
- `not_submitted` (no request yet)
- `pending_review`
- `verified`
- `rejected`

Write-side boundaries:
- `SubmitCourierVerificationRequestAction`
- `ApproveCourierVerificationRequestAction`
- `RejectCourierVerificationRequestAction`

Storage boundary:
- document files stored on non-public disk (`config/courier_verification.php`, default `local`)
- admin preview only via authenticated admin endpoint
- courier profile exposes status/reason only, not raw storage paths or permanent public URLs.

## Compatibility projection
`courier_verification_requests.status` is the canonical source of truth.

Compatibility mirrors:
- approve -> `users.is_verified=true`, `couriers.is_verified=true`
- reject -> `users.is_verified=false`, `couriers.is_verified=false`
- submit -> keeps compatibility flags false while awaiting review.

## Why isolated from hot profile fields
- avoids nullable review columns in `users`/`couriers`
- keeps profile render path read-only and compact
- keeps identity/contact updates separate from document moderation flow
- enables future moderation evolution without changing core user schema each iteration.

## Rollback notes
1. Disable courier profile verification submit CTA and admin verification resource.
2. Keep existing booleans (`users.is_verified`, `couriers.is_verified`) as temporary compatibility checks.
3. Optional rollback migration can drop `courier_verification_requests` when audit retention is not required.

## Phase 2 intentionally out of scope
- front/back multi-side packs
- selfie matching / OCR / AI parsing
- document expiry checks
- reminder/escalation jobs
- advanced compliance automation.
