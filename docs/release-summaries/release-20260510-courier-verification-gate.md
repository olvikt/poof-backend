# Release summary: courier verification gate before order dispatch

## Scope
- Enforced courier verification gate in dispatch candidate selection and available-orders read path.
- Added courier UX gate message with CTA to profile/verification.
- Added admin pending-review badge count for courier verification requests.
- Added feature coverage for unverified/pending/verified dispatch outcomes and UI gate visibility.

## Manual test steps
1. Login as courier with `users.is_verified=false` and `couriers.is_verified=false`; set online + fresh location.
2. Create paid searching order near courier and run dispatch trigger; confirm no `order_offers` created.
3. Open courier available orders; confirm verification gate copy and profile CTA are visible.
4. Submit courier verification document from profile; confirm new `courier_verification_requests` row in `pending_review`.
5. Login admin and open courier verification resource; confirm navigation badge increments for pending request.
6. Approve request in admin; confirm request status becomes `verified` and both flags are mirrored to true.
7. Re-run dispatch for paid searching order; confirm verified courier receives pending offer.
8. Reject another pending request; confirm rejection reason persists and flags are mirrored false.
