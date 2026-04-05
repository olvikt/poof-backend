# release-20260405-dispatch-correctness-live-offers-485

- PR #485
- Fix courier dispatch correctness for live pending offers and lock recheck
- Prevent false retry/backoff while order is waiting on a live pending offer
- Revalidate deferred next_dispatch_at under lock before dispatch attempt
