# release-20260405-order-promise-reentry-hotfix-488

- PR #488
- Make order promise migration re-entrant for partial SQLite applies
- Guard promise columns and orders_dispatch_validity_idx creation on re-entry
- Preserve safe migration recovery path for SQLite and MariaDB/MySQL index checks
