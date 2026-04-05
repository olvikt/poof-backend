# release-20260405-order-promise-auto-expire-486

- PR #486
- Add order promise layer with validity window and client wait preference
- Add auto-expire lifecycle for stale searching orders with cleanup of live pending offers
- Exclude expired orders from dispatch and courier available flow, and show promise metadata in client/courier UI
